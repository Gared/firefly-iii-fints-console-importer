<?php

declare(strict_types=1);

namespace Console;

use Gared\FireflyImporter\Console\GenerateConfigCommand;
use Gared\FireflyImporter\FinTS\FinTSFactory;
use Gared\FireflyImporter\FinTS\FinTSOptionsFactory;
use Gared\FireflyImporter\State\StateHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class GenerateConfigCommandTest extends TestCase
{
    private ApplicationTester $applicationTester;

    protected function setUp(): void
    {
        $finTsOptionsFactory = $this->createStub(FinTSOptionsFactory::class);
        $finTsFactory = $this->createStub(FinTSFactory::class);
        $stateHandler = $this->createStub(StateHandler::class);
        $responses = [
            JsonMockResponse::fromFile(__DIR__ . '/../Fixtures/Firefly/Accounts/single_account.json'),
        ];

        $httpClient = new MockHttpClient($responses);

        $application = new Application();
        $application->addCommands([
            new GenerateConfigCommand(
                stateHandler: $stateHandler,
                finTsOptionsFactory: $finTsOptionsFactory,
                finTsFactory: $finTsFactory,
                httpClient: $httpClient,
            ),
        ]);
        $application->setAutoExit(false);

        $this->applicationTester = new ApplicationTester($application);
    }

    public function testCommandSuccess(): void
    {
        $this->applicationTester->setInputs([
            'https://test.mybaaaank.com', // bank url
            '123 456 789', // bank code (with spaces is wrong)
            '123456789', // bank code (corrected input)
            'testaccount', // username
            'secr3t', // password
            'https://firefly.com/api', // firefly url
            'abc', // firefly access token
            '0', // select first firefly account
        ]);
        $this->applicationTester->run(
            ['command' => 'config:generate'],
        );

        $this->applicationTester->assertCommandIsSuccessful($this->applicationTester->getDisplay());
    }
}
