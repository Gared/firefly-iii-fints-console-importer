<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Config\Parser;

use DateTimeImmutable;
use DateTimeInterface;
use Gared\FireflyImporter\Config\Exception\InvalidConfigurationException;

class ConfigurationMapper
{
    /**
     * @param array<mixed> $data
     */
    public function mapFromData(array $data): Config
    {
        $chooseAccountAutomation = $this->getArrayValue($data, 'choose_account_automation');

        $account = new Account(
            iban: $this->getStringValue($chooseAccountAutomation, 'bank_account_iban'),
            fireflyAccountId: $this->getNumericStringValue($chooseAccountAutomation, 'firefly_account_id'),
            fromDate: new DateTimeImmutable($this->getStringValue($chooseAccountAutomation, 'from')),
            toDate: new DateTimeImmutable($this->getStringValue($chooseAccountAutomation, 'to')),
        );

        return new Config(
            username: $this->getStringValue($data, 'bank_username'),
            password: $this->getStringValue($data, 'bank_password'),
            code: $this->getStringValue($data, 'bank_code'),
            url: $this->getStringValue($data, 'bank_url'),
            bank2fa: $this->getStringValue($data, 'bank_2fa'),
            bank2faDevice: $this->getNullableStringValue($data, 'bank_2fa_device'),
            fireflyUrl: $this->getStringValue($data, 'firefly_url'),
            fireflyAccessToken: $this->getStringValue($data, 'firefly_access_token'),
            account: $account,
        );
    }

    /**
     * @return array<mixed>
     */
    public function mapToData(Config $config): array
    {
        return [
            'bank_username' => $config->username,
            'bank_password' => $config->password,
            'bank_code' => $config->code,
            'bank_url' => $config->url,
            'bank_2fa' => $config->bank2fa,
            'bank_2fa_device' => $config->bank2faDevice,
            'firefly_url' => $config->fireflyUrl,
            'firefly_access_token' => $config->fireflyAccessToken,
            'choose_account_automation' => [
                'bank_account_iban' => $config->account->iban,
                'firefly_account_id' => $config->account->fireflyAccountId,
                'from' => $config->account->fromDate->format(DateTimeInterface::ATOM),
                'to' => $config->account->toDate->format(DateTimeInterface::ATOM),
            ],
        ];
    }

    /**
     * @param array<mixed> $data
     */
    private function getStringValue(array $data, string $key): string
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidConfigurationException('Missing key: ' . $key);
        }

        if (is_int($data[$key])) {
            return (string) $data[$key];
        }

        if (!is_string($data[$key])) {
            throw new InvalidConfigurationException('Invalid type for key: ' . $key . '. Expected string.');
        }

        return $data[$key];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private function getArrayValue(array $data, string $key): array
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidConfigurationException('Missing key: ' . $key);
        }

        if (!is_array($data[$key])) {
            throw new InvalidConfigurationException('Invalid type for key: ' . $key . '. Expected array.');
        }

        return $data[$key];
    }

    /**
     * @param array<mixed> $data
     */
    private function getNullableStringValue(array $data, string $key): ?string
    {
        if (!array_key_exists($key, $data)) {
            throw new InvalidConfigurationException('Missing key: ' . $key);
        }

        if ($data[$key] === null) {
            return null;
        }

        if (!is_string($data[$key])) {
            throw new InvalidConfigurationException('Invalid type for key: ' . $key . '. Expected string or null.');
        }

        return $data[$key];
    }

    /**
     * @param array<mixed> $data
     *
     * @return numeric-string
     */
    private function getNumericStringValue(array $data, string $key): string
    {
        $value = $this->getStringValue($data, $key);

        if (!is_numeric($value)) {
            throw new InvalidConfigurationException('Invalid type for key: ' . $key . '. Expected numeric-string.');
        }

        return $value;
    }
}
