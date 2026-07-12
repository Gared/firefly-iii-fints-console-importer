<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Config;

use Gared\FireflyImporter\Config\Parser\ConfigurationMapper;

class ConfigFileHandlerFactory
{
    public function create(): ConfigFileHandler
    {
        $configurationMapper = new ConfigurationMapper();

        return new ConfigFileHandler($configurationMapper);
    }
}
