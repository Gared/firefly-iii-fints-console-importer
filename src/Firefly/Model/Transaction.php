<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Model;

readonly class Transaction
{
    /**
     * @param list<string>|null $tags
     */
    public function __construct(
        public string $type,
        public string $date,
        public string $amount,
        public string $description,
        public int $order = 0,
        public ?string $currencyId = null,
        public ?string $currencyCode = null,
        public ?string $foreignAmount = null,
        public ?string $foreignCurrencyId = null,
        public ?string $foreignCurrencyCode = null,
        public ?string $budgetId = null,
        public ?string $budgetName = null,
        public ?string $categoryId = null,
        public ?string $categoryName = null,
        public ?string $sourceId = null,
        public ?string $sourceName = null,
        public ?string $sourceIban = null,
        public ?string $destinationId = null,
        public ?string $destinationName = null,
        public ?string $destinationIban = null,
        public ?bool $reconciled = null,
        public ?int $piggyBankId = null,
        public ?string $piggyBankName = null,
        public ?string $billId = null,
        public ?string $billName = null,
        public ?array $tags = null,
        public ?string $notes = null,
        public ?string $internalReference = null,
        public ?string $externalId = null,
        public ?string $externalUrl = null,
        public ?string $sepaCC = null,
        public ?string $sepaCtOp = null,
        public ?string $sepaCtId = null,
        public ?string $sepaDb = null,
        public ?string $sepaCountry = null,
        public ?string $sepaEp = null,
        public ?string $sepaCi = null,
        public ?string $sepaBatchId = null,
        public ?string $interestDate = null,
        public ?string $bookDate = null,
        public ?string $processDate = null,
        public ?string $dueDate = null,
        public ?string $paymentDate = null,
        public ?string $invoiceDate = null,
    ) {
    }

    /**
     * @return array{
     *     type: string,
     *     date: string,
     *     description: string,
     *     order: int|null,
     * }
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->type,
            'date' => $this->date,
            'amount' => $this->amount,
            'description' => $this->description,
            'order' => $this->order,
            'currency_id' => $this->currencyId,
            'currency_code' => $this->currencyCode,
            'foreign_amount' => $this->foreignAmount,
            'foreign_currency_id' => $this->foreignCurrencyId,
            'foreign_currency_code' => $this->foreignCurrencyCode,
            'budget_id' => $this->budgetId,
            'budget_name' => $this->budgetName,
            'category_id' => $this->categoryId,
            'category_name' => $this->categoryName,
            'source_id' => $this->sourceId,
            'source_name' => $this->sourceName,
            'source_iban' => $this->sourceIban,
            'destination_id' => $this->destinationId,
            'destination_name' => $this->destinationName,
            'piggy_bank_id' => $this->piggyBankId,
            'piggy_bank_name' => $this->piggyBankName,
            'bill_id' => $this->billId,
            'bill_name' => $this->billName,
            'tags' => $this->tags,
            'notes' => $this->notes,
            'internal_reference' => $this->internalReference,
            'external_id' => $this->externalId,
            'external_url' => $this->externalUrl,
            'sepa_cc' => $this->sepaCC,
            'sepa_ct_op' => $this->sepaCtOp,
            'sepa_ct_id' => $this->sepaCtId,
            'sepa_db' => $this->sepaDb,
            'sepa_country' => $this->sepaCountry,
            'sepa_ep' => $this->sepaEp,
            'sepa_ci' => $this->sepaCi,
            'sepa_batch_id' => $this->sepaBatchId,
            'interest_date' => $this->interestDate,
            'book_date' => $this->bookDate,
            'process_date' => $this->processDate,
            'due_date' => $this->dueDate,
            'payment_date' => $this->paymentDate,
            'invoice_date' => $this->invoiceDate,
        ];

        if ($this->reconciled !== null) {
            $data['reconciled'] = $this->reconciled;
        }

        return $data;
    }
}
