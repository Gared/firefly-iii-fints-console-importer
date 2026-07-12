<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\FinTs;
use Fhp\Model\NoPsd2TanMode;
use Fhp\Options\Credentials;
use Gared\FireflyImporter\Config\Parser\Config;

class FinTSFactory
{
    public function __construct(
        private FinTSOptionsFactory $finTSOptionsFactory,
    ) {
    }

    public function create(Config $config, ?string $finTsSerialized = null): FinTs
    {
        $finTs = $this->createFromParameters(
            $config->url,
            $config->code,
            $config->username,
            $config->password,
            $finTsSerialized,
        );

        $bank2fa = $config->bank2fa;
        if ($bank2fa === 'NoPsd2TanMode') {
            $bank2fa = NoPsd2TanMode::ID;
        } else {
            $bank2fa = (int) $bank2fa;
        }

        $finTs->selectTanMode($bank2fa, $config->bank2faDevice);

        return $finTs;
    }

    public function createFromParameters(string $url, string $code, string $username, string $password, ?string $finTsSerialized = null): FinTs
    {
        $options = $this->finTSOptionsFactory->create($url, $code);

        $credentials = Credentials::create($username, $password);

        $finTs = FinTs::new($options, $credentials, $finTsSerialized);

        return $finTs;
    }
}
