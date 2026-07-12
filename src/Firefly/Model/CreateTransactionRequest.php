<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Model;

readonly class CreateTransactionRequest
{
    /**
     * @param list<Transaction> $transactions
     */
    public function __construct(
        public array $transactions,
        public bool $errorIfDuplicateHash = true,
        public bool $applyRules = true,
    ) {
    }

    /**
     * @return array{transactions: list<array>, error_if_duplicate_hash: bool, apply_rules: bool}
     */
    public function toArray(): array
    {
        return [
            'error_if_duplicate_hash' => $this->errorIfDuplicateHash,
            'apply_rules' => $this->applyRules,
            'transactions' => array_map(fn (Transaction $transaction) => $transaction->toArray(), $this->transactions),
        ];
    }
}
