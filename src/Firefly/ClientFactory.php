<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly;

use Gared\FireflyImporter\Config\Parser\Config;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClientFactory
{
    public function createFromConfig(Config $config, ?HttpClientInterface $httpClient = null): Client
    {
        if ($httpClient === null) {
            $httpClient = HttpClient::create([
                'max_redirects' => 0,
            ]);
        }

        return new Client(
            url: $config->fireflyUrl,
            accessToken: $config->fireflyAccessToken,
            httpClient: $httpClient,
        );
    }
}
