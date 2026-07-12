<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\Options\FinTsOptions;

class FinTSOptionsFactory
{
    public function create(string $url, string $bankCode): FinTsOptions
    {
        $options = new FinTsOptions();
        $options->url = $url;
        $options->bankCode = $bankCode;
        $options->productName = '0F4CA8A225AC9799E6BE3F334';
        $options->productVersion = '1.0';
        $options->validate();

        return $options;
    }
}
