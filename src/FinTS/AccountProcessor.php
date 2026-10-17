<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\Action\GetBalance;
use Fhp\Action\GetDepotAufstellung;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\Exception\FailedException;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\Firefly\Model\AccountType;
use Gared\FireflyImporter\Firefly\Model\CreateTransactionRequest;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use Gared\FireflyImporter\Firefly\Model\Transaction;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

readonly class AccountProcessor
{
    public function __construct(
        private TransactionMapper $transactionMapper = new TransactionMapper(),
        private AccountStatementLoader $accountStatementLoader = new AccountStatementLoader(),
    ) {
    }

    public function handle(
        FinTs $finTs,
        SEPAAccount $account,
        BankAccountType $accountType,
        Config $config,
        Client $fireflyClient,
        SymfonyStyle $io,
        bool $dryRun,
    ): void {
        $fireflyTransactions = $this->createTransactions($accountType, $io, $finTs, $account, $config, $fireflyClient);

        if ($dryRun) {
            $io->info('Dry run mode enabled. Transactions will not be sent.');

            return;
        }

        $io->info('Sending [' . count($fireflyTransactions) . '] transactions');
        $successCount = 0;
        foreach ($fireflyTransactions as $transaction) {
            if ($this->sendTransaction($fireflyClient, $transaction, $io)) {
                $successCount++;
            }
        }

        $io->info('Sent firefly transactions: ' . $successCount . '/' . count($fireflyTransactions) . ' successful');
    }

    /**
     * @return list<Transaction>
     */
    private function createTransactions(
        BankAccountType $accountType,
        SymfonyStyle $io,
        FinTs $finTs,
        SEPAAccount $account,
        Config $config,
        Client $fireflyClient,
    ): array {
        if ($accountType === BankAccountType::SECURITIES_ACCOUNT) {
            $io->info('The selected account is a depot account. Handle only balance difference.');

            return [$this->handleDepot($finTs, $account, $config, $fireflyClient, $io)];
        }

        if ($accountType === BankAccountType::FUND_ACCOUNT) {
            $io->info('The selected account is a fund account. Handle only balance difference.');

            return [$this->handleFund($finTs, $account, $config, $fireflyClient, $io)];
        }

        return $this->handleSepaAccount($finTs, $account, $config, $io);
    }

    /**
     * @return list<Transaction>
     */
    private function handleSepaAccount(FinTs $finTs, SEPAAccount $account, Config $config, SymfonyStyle $io): array
    {
        $statementAccount = $this->accountStatementLoader->getStatementOfAccount($account, $config, $finTs);

        $table = $io->createTable();
        $table->setHeaders(['Date', 'Credit/Debit', 'Amount', 'Description', 'Account Number', 'Name']);

        $fireflyTransactions = [];
        foreach ($statementAccount->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $transaction) {
                $table->addRow([
                    $transaction->getBookingDate()?->format('Y-m-d'),
                    $transaction->getCreditDebit(),
                    $transaction->getAmount(),
                    $transaction->getMainDescription(),
                    $transaction->getAccountNumber(),
                    $transaction->getName(),
                ]);

                $fireflyTransactions[] = $this->transactionMapper->mapFromBankTransaction($transaction, $config->account);
            }
        }
        $table->render();

        return $fireflyTransactions;
    }

    private function handleFund(FinTs $finTs, SEPAAccount $account, Config $config, Client $fireflyClient, SymfonyStyle $io): Transaction
    {
        $getBalance = GetBalance::create($account);
        $finTs->execute($getBalance);

        $balances = $getBalance->getBalances();

        $accountBalance = null;
        foreach ($balances as $balance) {
            if ($balance->getAccountInfo()->getAccountNumber() === $account->getAccountNumber()) {
                $accountBalance = $balance;
                break;
            }
        }

        if ($accountBalance === null) {
            throw new RuntimeException('Could not find balance for account ' . $account->getAccountNumber());
        }

        $io->info('Current balance: ' . $accountBalance->getGebuchterSaldo()->getAmount());

        $fireflyAccount = $this->getFireflyAccount($fireflyClient, $config);

        $correctionAmount = abs($accountBalance->getGebuchterSaldo()->getAmount() - $fireflyAccount->currentBalance);
        if ($correctionAmount === 0.0) {
            throw new RuntimeException('No correction needed. The depot value matches the current balance.');
        }

        return $this->transactionMapper->mapFromBankBalance($correctionAmount, $accountBalance, $config->account);
    }

    private function handleDepot(FinTs $finTs, SEPAAccount $account, Config $config, Client $fireflyClient, SymfonyStyle $io): Transaction
    {
        $getDepotAufstellung = GetDepotAufstellung::create($account);
        $finTs->execute($getDepotAufstellung);

        $statement = $getDepotAufstellung->getStatement();

        $io->info('Current balance: ' . $getDepotAufstellung->getDepotWert());

        $io->table(['Name', 'Amount', 'Price', 'Currency', 'Acquisition Price', 'ISIN', 'Date'], array_map(fn ($holding) => [
            $holding->getName(),
            $holding->getAmount(),
            $holding->getPrice(),
            $holding->getCurrency(),
            $holding->getAcquisitionPrice(),
            $holding->getISIN(),
        ], $statement->getHoldings()));

        $fireflyAccount = $this->getFireflyAccount($fireflyClient, $config);

        $correctionAmount = abs($getDepotAufstellung->getDepotWert() - $fireflyAccount->currentBalance);
        if ($correctionAmount === 0.0) {
            throw new RuntimeException('No correction needed. The depot value matches the current balance.');
        }

        return $this->transactionMapper->mapFromBankDepotAufstellung($correctionAmount, $getDepotAufstellung, $config->account);
    }

    private function getFireflyAccount(Client $fireflyClient, Config $config): FireflyAccount
    {
        $accounts = $fireflyClient->getAccounts(
            accountType: AccountType::Asset,
        );

        $fireflyAccount = null;
        foreach ($accounts as $account) {
            if ($account->id === $config->account->fireflyAccountId) {
                $fireflyAccount = $account;
                break;
            }
        }

        if ($fireflyAccount === null) {
            throw new RuntimeException('Firefly account not found. Please review your configuration file');
        }

        return $fireflyAccount;
    }

    private function sendTransaction(Client $fireflyClient, Transaction $transaction, SymfonyStyle $io): bool
    {
        try {
            $fireflyClient->postTransactions(new CreateTransactionRequest(
                transactions: [$transaction],
            ));
            $io->success('Successfully sent transaction');

            return true;
        } catch (FailedException $exception) {
            $io->error($exception->getMessage());
            foreach ($exception->errors as $errorType => $message) {
                $io->error($errorType . ': ' . print_r($message, true));
            }
        }

        return false;
    }
}
