<?php

declare(strict_types=1);

namespace Unit\Config\Parser;

use DateTimeImmutable;
use Gared\FireflyImporter\Config\Exception\InvalidConfigurationException;
use Gared\FireflyImporter\Config\Parser\ConfigurationMapper;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigurationMapperTest extends TestCase
{
    private ConfigurationMapper $configurationMapper;

    protected function setUp(): void
    {
        $this->configurationMapper = new ConfigurationMapper();
    }

    public function testMapFromDataWithout2fa(): void
    {
        $data = [
            'bank_username' => 'user01',
            'bank_password' => 'passw$rd',
            'bank_code' => '12345',
            'bank_url' => 'https://mybank.com',
            'bank_2fa' => 'NoPsd2TanMode',
            'bank_2fa_device' => '',
            'firefly_url' => 'https://firefly.com/',
            'firefly_access_token' => 'abc',
            'choose_account_automation' => [
                'bank_account_iban' => '',
                'firefly_account_id' => '3',
                'from' => '',
                'to' => '',
            ],
        ];

        $config = $this->configurationMapper->mapFromData($data);
        self::assertSame('user01', $config->username);
        self::assertSame('passw$rd', $config->password);
        self::assertSame('12345', $config->code);
        self::assertSame('https://mybank.com', $config->url);
        self::assertSame('NoPsd2TanMode', $config->bank2fa);
        self::assertSame('https://firefly.com/', $config->fireflyUrl);
        self::assertSame('abc', $config->fireflyAccessToken);
        self::assertSame('3', $config->account->fireflyAccountId);
        self::assertSame('', $config->account->iban);
        self::assertInstanceOf(DateTimeImmutable::class, $config->account->fromDate);
        self::assertInstanceOf(DateTimeImmutable::class, $config->account->toDate);
    }

    /**
     * @param list<mixed> $data
     */
    #[DataProvider('getInvalidData')]
    public function testMapFromDataWithInvalidData(array $data): void
    {
        self::expectException(InvalidConfigurationException::class);

        $this->configurationMapper->mapFromData($data);
    }

    public static function getInvalidData(): Generator
    {
        yield 'empty' => [[]];

        yield 'only bank_username' => [[
            'bank_username' => '123',
        ]];
    }
}
