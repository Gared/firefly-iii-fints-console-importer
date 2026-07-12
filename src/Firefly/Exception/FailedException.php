<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Exception;

use Exception;

class FailedException extends Exception
{
    /**
     * @param array<mixed> $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
