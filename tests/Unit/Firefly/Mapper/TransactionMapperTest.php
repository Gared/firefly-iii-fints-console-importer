<?php

declare(strict_types=1);

namespace Firefly\Mapper;

use DateTime;
use DateTimeImmutable;
use Fhp\Action\GetDepotAufstellung;
use Fhp\Model\StatementOfAccount\Transaction;
use Fhp\Model\StatementOfHoldings\Holding;
use Fhp\Model\StatementOfHoldings\StatementOfHoldings;
use Gared\FireflyImporter\Config\Parser\Account;
use Gared\FireflyImporter\Firefly\Mapper\TransactionMapper;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use PHPUnit\Framework\TestCase;

class TransactionMapperTest extends TestCase
{
    public function testMapFromBankTransactionWillUseTypeTransfer(): void
    {
        $transactionMapper = new TransactionMapper();

        $transaction = $this->createStub(Transaction::class);
        $transaction->method('getCreditDebit')->willReturn(Transaction::CD_CREDIT);
        $transaction->method('getBookingDate')->willReturn(new DateTime());
        $transaction->method('getAccountNumber')->willReturn('DE1234');

        $account = new Account(
            iban: 'DE999999',
            fireflyAccountId: '1',
            fromDate: new DateTimeImmutable(),
            toDate: new DateTimeImmutable(),
        );

        $assetAccount = new FireflyAccount(
            id: '123',
            iban: 'DE1234',
        );

        $fireflyTransaction = $transactionMapper->mapFromBankTransaction(
            transaction: $transaction,
            account: $account,
            assetAccounts: [$assetAccount],
        );

        self::assertSame('transfer', $fireflyTransaction->type);
        self::assertSame('123', $fireflyTransaction->sourceId);
        self::assertSame('1', $fireflyTransaction->destinationId);
    }

    public function testMapFromBankDepotAufstellung(): void
    {
        $transactionMapper = new TransactionMapper();

        $transaction = $this->createStub(Transaction::class);
        $transaction->method('getCreditDebit')->willReturn(Transaction::CD_CREDIT);
        $transaction->method('getBookingDate')->willReturn(new DateTime());
        $transaction->method('getAccountNumber')->willReturn('DE1234');

        $holding = $this->createStub(Holding::class);

        $statementOfHoldings = $this->createStub(StatementOfHoldings::class);
        $statementOfHoldings->method('getHoldings')
            ->willReturn([
                $holding,
            ]);

        $depotAufstellung = $this->createStub(GetDepotAufstellung::class);
        $depotAufstellung->method('getStatement')
            ->willReturn($statementOfHoldings);

        $account = new Account(
            iban: 'DE999999',
            fireflyAccountId: '1',
            fromDate: new DateTimeImmutable(),
            toDate: new DateTimeImmutable(),
        );

        $fireflyTransaction = $transactionMapper->mapFromBankDepotAufstellung(
            correctionAmount: -50.12,
            depotAufstellung: $depotAufstellung,
            account: $account,
        );

        self::assertSame('withdrawal', $fireflyTransaction->type);
        self::assertSame('50.12', $fireflyTransaction->amount);
        self::assertSame('1', $fireflyTransaction->sourceId);
        self::assertSame(null, $fireflyTransaction->destinationId);
    }
}
