<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Model;

use Stringable;

class FireflyAccount implements Stringable
{
    /**
     * @param numeric-string|null $id
     */
    public function __construct(
        public ?string $id = null,
        public ?string $iban = null,
        public ?string $name = null,
        public ?float $currentBalance = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->iban . ' ' . $this->name . ' (' . $this->id . ')';
    }
}
