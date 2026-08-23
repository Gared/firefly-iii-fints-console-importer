<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use DateTime;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\Action\GetStatementOfAccountXML;
use Fhp\CAMT\CAMT;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Model\StatementOfAccount\StatementOfAccount;
use Fhp\UnsupportedException;
use Gared\FireflyImporter\Config\ConfigFileHandlerFactory;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\FinTS\FinTSOptionsFactory;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\Exception\FailedException;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\Firefly\Model\CreateTransactionRequest;
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'If set, the command will not actually import transactions.')
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

        $account = $this->getAccount($finTs, $config);

        $statementAccount = $this->getStatementOfAccount($account, $config, $finTs);

        $table = new Table($output);

        $table->setHeaders(['Date', 'Credit/Debit', 'Amount', 'Description', 'Account Number', 'Name']);

        $transactionMapper = new TransactionMapper();

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

                $fireflyTransactions[] = $transactionMapper->mapFromBankTransaction($transaction, $config->account);
            }
        }
        $table->render();

        if ($input->getOption('dry-run')) {
            $io->info('Dry run mode enabled. Transactions will not be sent.');

            return self::SUCCESS;
        }

        $io->info('Sending [' . count($fireflyTransactions) . '] transactions');
        $successCount = 0;
        foreach ($fireflyTransactions as $transaction) {
            try {
                $fireflyClient->postTransactions(new CreateTransactionRequest(
                    transactions: [$transaction],
                ));
                $successCount++;
                $io->success('Successfully sent transaction');
            } catch (FailedException $exception) {
                $io->error($exception->getMessage());
                foreach ($exception->errors as $errorType => $message) {
                    $io->error($errorType . ': ' . print_r($message, true));
                }
            }
        }

        $io->info('Sent firefly transactions: ' . $successCount . '/' . count($fireflyTransactions) . ' successful');

        return self::SUCCESS;
    }

    private function getStatementOfAccount(SEPAAccount $account, Config $config, FinTs $finTs): StatementOfAccount
    {
        try {
            $getStatementOfAccountRequestXML = GetStatementOfAccountXML::create(
                account: $account,
                from: DateTime::createFromInterface($config->account->fromDate),
                to: DateTime::createFromInterface($config->account->toDate)
            );
            $finTs->execute($getStatementOfAccountRequestXML);
            $bookedXML = $getStatementOfAccountRequestXML->getBookedXML();

            $parser = new CAMT();
            $parsedCAMT = $parser->parse($bookedXML);

            return StatementOfAccount::fromCAMTArray($parsedCAMT);
        } catch (UnsupportedException) {
            $getStatementOfAccountRequest = GetStatementOfAccount::create(
                account: $account,
                from: DateTime::createFromInterface($config->account->fromDate),
                to: DateTime::createFromInterface($config->account->toDate)
            );
            $finTs->execute($getStatementOfAccountRequest);

            return $getStatementOfAccountRequest->getStatement();
        }
    }

    private function getAccount(FinTs $finTs, Config $config): SEPAAccount
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
}
