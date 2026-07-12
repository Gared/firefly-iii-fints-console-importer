<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Config\Parser;

readonly class Config
{
    public function __construct(
        public string $username,
        public string $password,
        public string $code,
        public string $url,
        public string $bank2fa,
        public ?string $bank2faDevice,
        public string $fireflyUrl,
        public string $fireflyAccessToken,
        public Account $account,
    ) {
    }
}
