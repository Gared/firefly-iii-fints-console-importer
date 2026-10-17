<?php

declare(strict_types=1);

namespace FinTS;

use DateTimeImmutable;
use Fhp\Action\GetBalance;
use Fhp\Action\GetDepotAufstellung;
use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Model\StatementOfAccount\Statement;
use Fhp\Model\StatementOfAccount\StatementOfAccount;
use Fhp\Model\StatementOfAccount\Transaction;
use Fhp\Model\StatementOfHoldings\StatementOfHoldings;
use Fhp\Segment\Common\AccountInfo;
use Fhp\Segment\Common\Sdo;
use Fhp\Segment\SAL\HISAL;
use Gared\FireflyImporter\Config\Parser\Account;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\FinTS\AccountProcessor;
use Gared\FireflyImporter\FinTS\AccountStatementLoader;
use Gared\FireflyImporter\FinTS\BankAccountType;
use Gared\FireflyImporter\Firefly\Client;
use Gared\FireflyImporter\Firefly\ClientFactory;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\MockHttpClient;

class AccountProcessorTest extends TestCase
{
    public function testSepaAccount(): void
    {
        $accountStatementLoader = $this->createMock(AccountStatementLoader::class);
        $statement = $this->createStub(Statement::class);
        $statement->method('getTransactions')
            ->willReturn([
                $this->createStub(Transaction::class),
            ]);
        $statementOfAccount = $this->createStub(StatementOfAccount::class);
        $statementOfAccount->method('getStatements')
            ->willReturn([
                $statement,
            ]);
        $accountStatementLoader
            ->expects(self::once())
            ->method('getStatementOfAccount')
            ->willReturn($statementOfAccount);
        $fireflyClient = $this->getMockBuilder(Client::class)
            ->setConstructorArgs(['', '', new MockHttpClient()])
            ->getMock();
        $fireflyClient->expects(self::once())->method('getAccounts');
        $clientFactory = $this->createStub(ClientFactory::class);
        $clientFactory->method('createFromConfig')->willReturn($fireflyClient);

        $processor = new AccountProcessor(
            transactionMapper: $this->createStub(TransactionMapper::class),
            accountStatementLoader: $accountStatementLoader,
            clientFactory: $clientFactory,
        );

        $finTs = $this->createStub(FinTs::class);

        $io = $this->createStub(SymfonyStyle::class);

        $account = new SEPAAccount();

        $config = $this->createConfig();

        $processor->handle(
            finTs: $finTs,
            account: $account,
            accountType: BankAccountType::SAVINGS,
            config: $config,
            io: $io,
            dryRun: false,
        );
    }

    #[DataProvider('getAccountTypes')]
    public function testDryRun(BankAccountType $accountType): void
    {
        $accountStatementLoader = $this->createStub(AccountStatementLoader::class);

        $fireflyClient = $this->getStubBuilder(Client::class)
            ->setConstructorArgs(['', '', new MockHttpClient()])
            ->getStub();
        $fireflyClient->method('getAccounts')
            ->willReturn([
                new FireflyAccount(
                    id: '123',
                    iban: 'DE1234',
                    name: 'Test',
                    currentBalance: 123.1,
                ),
            ]);
        $clientFactory = $this->createStub(ClientFactory::class);
        $clientFactory->method('createFromConfig')->willReturn($fireflyClient);

        $processor = new AccountProcessor(
            transactionMapper: $this->createStub(TransactionMapper::class),
            accountStatementLoader: $accountStatementLoader,
            clientFactory: $clientFactory,
        );

        $balance = $this->createStub(HISAL::class);
        $sdo = $this->createStub(Sdo::class);
        $sdo->method('getAmount')->willReturn(999.99);
        $balance->method('getGebuchterSaldo')->willReturn($sdo);

        $accountInfo = $this->createStub(AccountInfo::class);
        $accountInfo->method('getAccountNumber')->willReturn('DE1234');
        $balance->method('getAccountInfo')->willReturn($accountInfo);
        $balances = [$balance];

        $statement = $this->createStub(StatementOfHoldings::class);
        $statement->method('getHoldings')->willReturn([]);

        $finTs = $this->createStub(FinTs::class);
        $finTs->method('execute')->willReturnCallback(function (BaseAction $action) use ($balances, $statement): void {
            if ($action instanceof GetDepotAufstellung) {
                $this->setReflectionProperty($action, 'statement', $statement);
                $this->setReflectionProperty($action, 'isDone', true);
            }
            if ($action instanceof GetBalance) {
                $this->setReflectionProperty($action, 'response', $balances);
                $this->setReflectionProperty($action, 'isDone', true);
            }
        });

        $io = $this->createStub(SymfonyStyle::class);
        $fireflyClient = $this->createMock(Client::class);
        $fireflyClient->expects(self::never())->method('postTransactions');
        $fireflyClient->method('getAccounts')->willReturn([
            new FireflyAccount(
                id: '123',
                iban: 'DE1234',
                name: 'Test',
                currentBalance: 123.1,
            ),
        ]);

        $account = new SEPAAccount();
        $account->setAccountNumber('DE1234');

        $config = $this->createConfig();

        $processor->handle(
            finTs: $finTs,
            account: $account,
            accountType: $accountType,
            config: $config,
            io: $io,
            dryRun: true,
        );
    }

    private function setReflectionProperty(BaseAction $action, string $property, mixed $value): void
    {
        $reflectionProperty = new ReflectionProperty($action::class, $property);
        $reflectionProperty->setValue($action, $value);
    }

    /**
     * @return array<string, array{0: BankAccountType}>
     */
    public static function getAccountTypes(): array
    {
        return [
            'sepa account' => [BankAccountType::SAVINGS],
            'securities account' => [BankAccountType::SECURITIES_ACCOUNT],
            'fund account' => [BankAccountType::FUND_ACCOUNT],
        ];
    }

    private function createConfig(): Config
    {
        $account = new Account(
            iban: 'DE1234',
            fireflyAccountId: '123',
            fromDate: new DateTimeImmutable(),
            toDate: new DateTimeImmutable(),
        );

        return new Config(
            username: 'testuser',
            password: 'secret',
            code: '123456',
            url: 'https://mybank.de',
            bank2fa: '999',
            bank2faDevice: 'Device',
            fireflyUrl: 'https://firefly.example.com',
            fireflyAccessToken: '123',
            account: $account,
        );
    }
}
