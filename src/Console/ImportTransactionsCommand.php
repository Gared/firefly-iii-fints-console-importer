<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use Fhp\Segment\HIUPD\HIUPD;
use Gared\FireflyImporter\Config\ConfigFileHandlerFactory;
use Gared\FireflyImporter\FinTS\AccountLoader;
use Gared\FireflyImporter\FinTS\AccountProcessor;
use Gared\FireflyImporter\FinTS\AccountStatementLoader;
use Gared\FireflyImporter\FinTS\BankAccountType;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\FinTS\FinTSOptionsFactory;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\State\StateHandler;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(name: 'import-transactions')]
class ImportTransactionsCommand extends Command
{
    public function __construct(
        private readonly StateHandler $stateHandler = new StateHandler(),
        private readonly FinTSFactory $finTsFactory = new FinTSFactory(new FinTSOptionsFactory()),
        private readonly ConfigFileHandlerFactory $configFileHandlerFactory = new ConfigFileHandlerFactory(),
        private readonly AccountLoader $accountLoader = new AccountLoader(),
        private readonly AccountProcessor $accountProcessor = new AccountProcessor(new TransactionMapper(), new AccountStatementLoader()),
    ) {
        parent::__construct();
    }

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

        $configFileHandler = $this->configFileHandlerFactory->create();
        $config = $configFileHandler->load($configPath);

        $finTs = $this->finTsFactory->create($config, $this->stateHandler->load($config->code));
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

        if ($upd === null) {
            $io->error('UPD information not found.');

            return self::FAILURE;
        }

        $account = $this->accountLoader->load($finTs, $upd, $config);

        $hiupd = $upd->findHiupd($account);
        if ($hiupd instanceof HIUPD === false) {
            $io->error('HIUPD information not found.');

            return self::FAILURE;
        }

        $accountType = BankAccountType::fromHiupd($hiupd);

        $this->accountProcessor->handle(
            finTs: $finTs,
            account: $account,
            accountType: $accountType,
            config: $config,
            fireflyClient: $fireflyClient,
            io: $io,
            dryRun: $input->getOption('dry-run') === true,
        );

        return self::SUCCESS;
    }
}
