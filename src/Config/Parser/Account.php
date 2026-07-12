<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Config\Parser;

use DateTimeImmutable;

class Account
{
    /**
     * @param numeric-string $fireflyAccountId
     */
    public function __construct(
        public string $iban,
        public string $fireflyAccountId,
        public DateTimeImmutable $fromDate,
        public DateTimeImmutable $toDate,
    ) {
    }
}
