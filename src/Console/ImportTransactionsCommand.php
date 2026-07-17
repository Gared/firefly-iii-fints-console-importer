<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use DateTime;
use Fhp\Action\GetDepotAufstellung;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Segment\HIUPD\HIUPDv4;
use Fhp\Segment\HIUPD\HIUPDv6;
use Gared\FireflyImporter\Config\ConfigFileHandlerFactory;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\FinTS\FinTSOptionsFactory;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\Exception\FailedException;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\Firefly\Model\AccountType;
use Gared\FireflyImporter\Firefly\Model\CreateTransactionRequest;
use Gared\FireflyImporter\Firefly\Model\Transaction;
use Gared\FireflyImporter\State\StateHandler;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'import-transactions')]
class ImportTransactionsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Imports transactions from a FinTS account to Firefly III.')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the configuration file.')
            ->setHelp('This command allows you to import transactions from a FinTS account to Firefly III...');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configPath = $input->getOption('config');
        if (is_string($configPath) === false) {
            throw new InvalidArgumentException('The --config option is required and must be a string.');
        }

        $output->writeln('Running the configuration file: ' . $configPath);

        $configFileHandlerFactory = new ConfigFileHandlerFactory();
        $configFileHandler = $configFileHandlerFactory->create();
        $config = $configFileHandler->load($configPath);

        $stateHandler = new StateHandler();

        $finTsFactory = new FinTSFactory(new FinTSOptionsFactory());
        $finTs = $finTsFactory->create($config, $stateHandler->load($config->code));
        $finTs->setLogger(new ConsoleLogger($output));
        $finTs->forgetDialog();

        $login = $finTs->login();
        $upd = $login->getUpd();

        $httpClient = HttpClient::create([
            'max_redirects' => 0,
        ]);
        if ($httpClient instanceof LoggerAwareInterface) {
            $httpClient->setLogger(new ConsoleLogger($output));
        }

        $fireflyClient = new Client(
            url: $config->fireflyUrl,
            accessToken: $config->fireflyAccessToken,
            httpClient: $httpClient,
        );

        $account = $this->getSepaAccount($finTs, $config);

        if ($upd === null) {
            $io->error('UDP information not found.');

            return self::FAILURE;
        }

        $hiupd = $upd->findHiupd($account);
        if ($hiupd instanceof HIUPDv6 === false && $hiupd instanceof HIUPDv4 === false) {
            $io->error('HIUPD information not found.');

            return self::FAILURE;
        }

        if (str_contains($hiupd->kontoproduktbezeichnung ?? '', 'Depot')) {
            $io->info('The selected account is a depot account. Handle only balance difference.');

            $this->handleDepot($finTs, $account, $config, $fireflyClient, $io);

            return self::FAILURE;
        }

        $getStatementOfAccountRequest = GetStatementOfAccount::create(
            account: $account,
            from: DateTime::createFromInterface($config->account->fromDate),
            to: DateTime::createFromInterface($config->account->toDate)
        );
        $finTs->execute($getStatementOfAccountRequest);
        $statementAccount = $getStatementOfAccountRequest->getStatement();

        $table = new Table($output);

        $table->setHeaders(['Credit/Debit', 'Amount', 'Description', 'Account Number', 'Name']);

        $transactionMapper = new TransactionMapper();

        $fireflyTransactions = [];
        foreach ($statementAccount->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $transaction) {
                $table->addRow([
                    $transaction->getCreditDebit(),
                    $transaction->getAmount(),
                    $transaction->getMainDescription(),
                    $transaction->getAccountNumber(),
                    $transaction->getName(),
                ]);

                $fireflyTransactions[] = $transactionMapper->mapFromBankTransaction($transaction, $config->account);
            }
        }
        $table->render();

        $io->info('Sending [' . count($fireflyTransactions) . '] transactions');
        $successCount = 0;
        foreach ($fireflyTransactions as $transaction) {
            if ($this->sendTransaction($fireflyClient, $transaction, $io)) {
                $successCount++;
            }
        }

        $io->info('Sent firefly transactions: ' . $successCount . '/' . count($fireflyTransactions) . ' successful');

        return self::SUCCESS;
    }

    private function getSepaAccount(FinTs $finTs, Config $config): SEPAAccount
    {
        $getSepaAccountsAction = GetSEPAAccounts::create();
        $finTs->execute($getSepaAccountsAction);
        $accounts = $getSepaAccountsAction->getAccounts();

        foreach ($accounts as $account) {
            if ($account->getIban() === $config->account->iban) {
                return $account;
            }
        }

        throw new RuntimeException('Account not found. Please review your configuration file');
    }

    private function handleDepot(FinTs $finTs, SEPAAccount $account, Config $config, Client $fireflyClient, SymfonyStyle $io): void
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
            throw new RuntimeException('Account not found. Please review your configuration file');
        }

        $correctionAmount = abs($getDepotAufstellung->getDepotWert() - $fireflyAccount->currentBalance);
        if ($correctionAmount === 0.0) {
            $io->warning('No correction needed. The depot value matches the current balance.');

            return;
        }

        $transactionMapper = new TransactionMapper();
        $reconciliationTransaction = $transactionMapper->mapFromBankDepotAufstellung($correctionAmount, $getDepotAufstellung, $config->account);

        $this->sendTransaction($fireflyClient, $reconciliationTransaction, $io);
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
