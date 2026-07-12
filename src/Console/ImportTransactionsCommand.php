<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use DateTime;
use Fhp\Action\GetSEPAAccounts;
use Fhp\Action\GetStatementOfAccount;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
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

        $getStatementOfAccountRequest = GetStatementOfAccount::create(
            account: $account,
            from: DateTime::createFromInterface($config->account->fromDate),
            to: DateTime::createFromInterface($config->account->toDate)
        );
        $finTs->execute($getStatementOfAccountRequest);
        $statementAccount = $getStatementOfAccountRequest->getStatement();

        $table = new Table($output);

        $table->setHeaders(['Amount', 'Booking Text', 'Credit/Debit']);

        $transactionMapper = new TransactionMapper();

        $fireflyTransactions = [];
        foreach ($statementAccount->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $transaction) {
                $table->addRow([
                    $transaction->getAmount(),
                    $transaction->getMainDescription(),
                    $transaction->getCreditDebit(),
                ]);

                $fireflyTransaction = $transactionMapper->mapFromBankTransaction($transaction, $config->account);
                if (array_key_exists($fireflyTransaction->type, $fireflyTransactions) === false) {
                    $fireflyTransactions[$fireflyTransaction->type] = [];
                }
                $fireflyTransactions[$fireflyTransaction->type][] = $fireflyTransaction;
            }
        }
        $table->render();

        foreach ($fireflyTransactions as $type => $transactions) {
            $io->info('Sending transaction with type [' . $type . '] to firefly');
            try {
                $fireflyClient->postTransactions(new CreateTransactionRequest(
                    transactions: $transactions,
                ));
                $io->success('Successfully sent ' . count($transactions) . ' transactions');
            } catch (FailedException $exception) {
                $io->error($exception->getMessage());
                foreach ($exception->errors as $errorType => $message) {
                    $io->error($errorType . ': ' . print_r($message, true));
                }
            }
        }

        return self::SUCCESS;
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
