<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Mapper;

use DateTimeInterface;
use Exception;
use Fhp\Model\StatementOfAccount\Transaction;
use Gared\FireflyImporter\Config\Parser\Account;
use Gared\FireflyImporter\FinTS\StructuredDescriptionCodes;
use Gared\FireflyImporter\Firefly\Model\FireflyAccount;
use Gared\FireflyImporter\Firefly\Model\Transaction as FireflyTransaction;
use RuntimeException;

class TransactionMapper
{
    public function mapFromBankTransaction(Transaction $transaction, Account $account): FireflyTransaction
    {
        $type = match ($transaction->getCreditDebit()) {
            Transaction::CD_CREDIT => 'deposit',
            Transaction::CD_DEBIT => 'withdrawal',
            default => throw new Exception('Unknown transaction type: ' . $transaction->getCreditDebit()),
        };

        $sourceAccount = $this->getFireflyAccount($account, $transaction, $type, 'source');
        $destinationAccount = $this->getFireflyAccount($account, $transaction, $type, 'destination');

        if ($transaction->getBookingDate() === null) {
            throw new RuntimeException('No booking date in transaction set. Unable to forward transaction to firefly');
        }

        return new FireflyTransaction(
            type: $type,
            date: $transaction->getBookingDate()->format(DateTimeInterface::ATOM),
            amount: (string) $transaction->getAmount(),
            description: $this->getDescription($transaction),
            currencyCode: $transaction->getStructuredDescription()['CURR'] ?? null,
            sourceId: $sourceAccount->id,
            sourceName: $sourceAccount->name,
            sourceIban: $sourceAccount->iban,
            destinationId: $destinationAccount->id,
            destinationName: $destinationAccount->name,
            destinationIban: $destinationAccount->iban,
            notes: 'Created by fints-console-importer',
            sepaCtId: $transaction->getEndToEndID(),
            sepaDb: $transaction->getStructuredDescription()[StructuredDescriptionCodes::Mandatsreferenznummer->value] ?? null,
            sepaCi: $transaction->getStructuredDescription()[StructuredDescriptionCodes::CreditorIdentifier->value] ?? null,
            bookDate: $transaction->getValutaDate()?->format(DateTimeInterface::ATOM),
        );
    }

    private function getDescription(Transaction $transaction): string
    {
        $description = $transaction->getMainDescription();
        if ($description == '') {
            $description = $transaction->getBookingText();
        }

        return $description;
    }

    private function getFireflyAccount(Account $account, Transaction $transaction, string $type, string $accountType): FireflyAccount
    {
        if (
            ($accountType === 'source' && $type === 'withdrawal')
            || ($accountType === 'destination' && $type === 'deposit')
        ) {
            return new FireflyAccount(
                id: $account->fireflyAccountId,
            );
        }

        return new FireflyAccount(
            iban: $transaction->getAccountNumber(),
            name: $transaction->getName(),
        );
    }
}
