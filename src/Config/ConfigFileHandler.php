<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Config;

use Gared\FireflyImporter\Config\Exception\ConfigPersistException;
use Gared\FireflyImporter\Config\Exception\InvalidConfigurationException;
use Gared\FireflyImporter\Config\Parser\Config;
use Gared\FireflyImporter\Config\Parser\ConfigurationMapper;

class ConfigFileHandler
{
    public function __construct(
        private ConfigurationMapper $configurationMapper,
    ) {
    }

    public function persist(string $fileName, Config $config): void
    {
        $filePath = $this->getFilePath($fileName . '.json');
        $data = $this->configurationMapper->mapToData($config);
        $writtenBytes = file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
        if ($writtenBytes === false) {
            throw new ConfigPersistException('Failed to persist state to file: ' . $filePath);
        }
    }

    public function load(string $filePath): Config
    {
        if (file_exists($filePath) === false) {
            throw new InvalidConfigurationException('No persisted state found for file: ' . $filePath);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidConfigurationException('Unable to read file: ' . $filePath);
        }

        $configData = json_decode($content, true);
        if ($configData === null) {
            throw new InvalidConfigurationException('Invalid JSON in config file: ' . $filePath);
        }
        if (is_array($configData) === false) {
            throw new InvalidConfigurationException('Invalid JSON in config file: ' . $filePath);
        }

        return $this->configurationMapper->mapFromData($configData);
    }

    private function getFilePath(string $fileName): string
    {
        return __DIR__ . '/../../data/config/' . $fileName;
    }
}
