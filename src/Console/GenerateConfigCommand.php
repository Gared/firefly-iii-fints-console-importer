<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use DateTimeImmutable;
use Fhp\Model\NoPsd2TanMode;
use Fhp\Protocol\ServerException;
use Gared\FireflyImporter\Config\ConfigFileHandlerFactory;
use Gared\FireflyImporter\Config\Parser\Account;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\FinTS\FinTSOptionsFactory;
use Gared\FireflyImporter\FinTS\TanModeHandler;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\Model\AccountType;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use Gared\FireflyImporter\State\StateHandler;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Validator\Constraints as Assert;

#[AsCommand(name: 'config:generate')]
class GenerateConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Generate a new configuration file')
            ->setHelp('This command allows you to generate a new configuration file interactively');
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument]
        #[Ask(question: 'Enter the url to your bank FinTS API', constraints: [new Assert\Url()])]
        string $bankUrl,
        #[Argument]
        #[Ask(question: 'Enter the code of your bank (BLZ)', constraints: [new Assert\Regex('/[0-9]+/')])]
        string $bankCode,
        #[Argument]
        #[Ask(question: 'Enter the username that you use to login to your bank', constraints: [new Assert\NotBlank()])]
        string $username,
        #[Argument]
        #[Ask(question: 'Enter the password that you use to login to your bank', hidden: true, constraints: [new Assert\NotBlank()])]
        string $password,
        #[Argument]
        #[Ask(question: 'Enter the url to your firefly instance', constraints: [new Assert\Url()])]
        string $fireflyUrl,
        #[Argument]
        #[Ask(question: 'Enter the personal access token to connect to your firefly instance', constraints: [new Assert\NotBlank()])]
        string $fireflyAccessToken,
    ): int {
        $stateHandler = new StateHandler();
        $configFileHandlerFactory = new ConfigFileHandlerFactory();
        $configFileHandler = $configFileHandlerFactory->create();

        $finTsOptionsFactory = new FinTSOptionsFactory();
        $finTsFactory = new FinTSFactory($finTsOptionsFactory);
        $finTsOptions = $finTsOptionsFactory->create($bankUrl, $bankCode);

        $finTs = $finTsFactory->createFromParameters(
            url: $bankUrl,
            code: $bankCode,
            username: $username,
            password: $password,
        );

        try {
            $tanModes = $finTs->getTanModes();
        } catch (ServerException $exception) {
            $io->error($exception->getMessage());
            $tanModes = [];
        }

        if (count($tanModes) === 0) {
            $io->info('No tan modes supported - using login without tan mode');
            $tanMode = new NoPsd2TanMode();
        } elseif (count($tanModes) === 1) {
            $tanMode = array_first($tanModes);
            $io->info('Only one tan mode found. Using tan mode: ' . $tanMode->getName());
        } else {
            $displayChoices = array_map(fn ($mode) => $mode->getName(), $tanModes);
            $choiceQuestion = new ChoiceQuestion('Which tan mode do you want to use?', $displayChoices);
            $tanModeName = $io->askQuestion($choiceQuestion);

            $selectedKey = array_search($tanModeName, $displayChoices);
            $tanMode = $tanModes[$selectedKey];
        }

        $tanMedium = null;
        if ($tanMode->needsTanMedium()) {
            $tanMedia = $finTs->getTanMedia($tanMode);
            if (empty($tanMedia)) {
                $io->error('Tan media not found');

                return self::FAILURE;
            }

            if (count($tanMedia) === 1) {
                $tanMedium = array_first($tanMedia);
                $io->info('Only one tan media found. Using: ' . $tanMedium->getName());
            } else {
                $displayChoices = array_map(fn ($tanMedium) => $tanMedium->getName(), $tanMedia);
                $choiceQuestion = new ChoiceQuestion('Which tan media do you want to use?', $displayChoices);
                $tanMediumName = $io->askQuestion($choiceQuestion);

                $selectedKey = array_search($tanMediumName, $displayChoices);
                $tanMedium = $tanMedia[$selectedKey];
            }
        }

        $finTs->selectTanMode($tanMode, $tanMedium);
        $finTs->setLogger(new ConsoleLogger($io));
        $login = $finTs->login();

        $tanModeHandler = new TanModeHandler();
        $tanModeHandler->handle($finTs, $login, $io);

        $stateHandler->persist($finTs, $finTsOptions);

        $fireflyClient = new Client(
            url: $fireflyUrl,
            accessToken: $fireflyAccessToken,
            httpClient: HttpClient::create([
                'max_redirects' => 0,
            ]),
        );

        $accounts = $fireflyClient->getAccounts(
            accountType: AccountType::Asset,
        );

        /** @var FireflyAccount $account */
        $account = $io->askQuestion(new ChoiceQuestion('Which account do you want to use?', $accounts));
        if ($account->id === null) {
            $io->error('Account has no id set: Aborting');
            throw new RuntimeException('Account has no id set');
        }

        $question = new Question('Please enter the file name: ', 'mybank');
        $fileName = $io->askQuestion($question);
        if (is_string($fileName) === false) {
            throw new RuntimeException('File name must be a string');
        }

        $config = new Config(
            username: $username,
            password: $password,
            code: $bankCode,
            url: $bankUrl,
            bank2fa: (string) $tanMode->getId(),
            bank2faDevice: $tanMedium?->getName(),
            fireflyUrl: $fireflyUrl,
            fireflyAccessToken: $fireflyAccessToken,
            account: new Account(
                iban: $account->iban ?? '',
                fireflyAccountId: $account->id,
                fromDate: new DateTimeImmutable('-1 month'),
                toDate: new DateTimeImmutable('now'),
            )
        );

        $configFileHandler->persist($fileName, $config);

        $io->success('Successfully created configuration file: ' . $fileName);

        return self::SUCCESS;
    }
}
