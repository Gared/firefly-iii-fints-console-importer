<?php

declare(strict_types=1);

namespace Console;

use Fhp\Connection;
use Fhp\FinTs;
use Fhp\Model\NoPsd2TanMode;
use Fhp\Options\Credentials;
use Fhp\Options\FinTsOptions;
use Gared\FireflyImporter\Console\ImportTransactionsCommand;
use Gared\FireflyImporter\FinTS\AccountProcessor;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\ClientFactory;
use Gared\FireflyImporter\State\StateHandler;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ImportTransactionsCommandTest extends TestCase
{
    private ImportTransactionsCommand $command;
    private StateHandler&Stub $stateHandler;
    private FinTSFactory&Stub $finTsFactory;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->stateHandler = $this->createStub(StateHandler::class);
        $this->finTsFactory = $this->createStub(FinTSFactory::class);

        $clientFactory = $this->createStub(ClientFactory::class);
        $responses = [
            MockResponse::fromFile(__DIR__ . '/../Fixtures/Firefly/Accounts/single_account.json'),
        ];
        $this->httpClient = new MockHttpClient($responses);
        $clientFactory->method('createFromConfig')->willReturn(
            new Client(
                url: 'http://localhost',
                accessToken: '',
                httpClient: $this->httpClient,
            )
        );

        $this->command = new ImportTransactionsCommand(
            stateHandler: $this->stateHandler,
            finTsFactory: $this->finTsFactory,
            accountProcessor: new AccountProcessor(clientFactory: $clientFactory)
        );
    }

    public function testCommandSuccessWithXML(): void
    {
        $this->stateHandler->method('load')
            ->willReturn(file_get_contents(__DIR__ . '/../Fixtures/states/fake.txt'));

        $options = new FinTsOptions();
        $options->url = 'test';
        $options->bankCode = 'test';
        $options->productName = 'test';
        $options->productVersion = '1.0';
        $credentials = Credentials::create('test', 'test');

        $finTs = FinTs::new($options, $credentials);
        $finTs->selectTanMode(NoPsd2TanMode::ID);

        $connection = $this->createStub(Connection::class);
        $connection->method('send')
            ->willReturnOnConsecutiveCalls(
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/sync.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/sync_end.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/init_with_HKCAZ.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/get_accounts_with_HKCAZ.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/camt/get_statement.txt'),
            );

        $reflectionObject = new ReflectionObject($finTs);
        $reflectionProperty = $reflectionObject->getProperty('connection');
        $reflectionProperty->setValue($finTs, $connection);

        $this->finTsFactory->method('create')
            ->willReturn($finTs);

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--dry-run' => true,
            '--config' => __DIR__ . '/../Fixtures/config/valid.json',
        ]);

        $result = $this->command->run($input, $output);
        self::assertSame(Command::SUCCESS, $result);
    }

    public function testCommandSuccessWhenXMLNotAvailable(): void
    {
        $this->stateHandler->method('load')
            ->willReturn(file_get_contents(__DIR__ . '/../Fixtures/states/fake.txt'));

        $options = new FinTsOptions();
        $options->url = 'test';
        $options->bankCode = 'test';
        $options->productName = 'test';
        $options->productVersion = '1.0';
        $credentials = Credentials::create('test', 'test');

        $finTs = FinTs::new($options, $credentials);
        $finTs->selectTanMode(NoPsd2TanMode::ID);

        $connection = $this->createStub(Connection::class);
        $connection->method('send')
            ->willReturnOnConsecutiveCalls(
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/sync.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/sync_end.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/init.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/get_accounts.txt'),
                file_get_contents(__DIR__ . '/../Fixtures/FinTS/responses/mt940/get_statement.txt'),
            );

        $reflectionObject = new ReflectionObject($finTs);
        $reflectionProperty = $reflectionObject->getProperty('connection');
        $reflectionProperty->setValue($finTs, $connection);

        $this->finTsFactory->method('create')
            ->willReturn($finTs);

        $output = new BufferedOutput();
        $input = new ArrayInput([
            '--dry-run' => true,
            '--config' => __DIR__ . '/../Fixtures/config/valid.json',
        ]);

        $result = $this->command->run($input, $output);
        self::assertSame(Command::SUCCESS, $result);
    }
}
