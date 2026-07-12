<?php

declare(strict_types=1);

namespace Integration\Console;

use Gared\FireflyImporter\Config\Exception\InvalidConfigurationException;
use Gared\FireflyImporter\Console\ValidateConfigCommand;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ValidateConfigCommandTest extends TestCase
{
    private ValidateConfigCommand $command;

    protected function setUp(): void
    {
        $this->command = new ValidateConfigCommand();
    }

    public function testCommandSuccess(): void
    {
        $output = new BufferedOutput();
        $input = new ArrayInput([
            'file' => __DIR__ . '/../Fixtures/config/valid.json',
        ]);

        $result = $this->command->run($input, $output);
        self::assertSame(Command::SUCCESS, $result);
    }

    #[DataProvider('getInvalidFiles')]
    public function testCommandWillFailWithInvalidConfig(string $filePath): void
    {
        $output = new BufferedOutput();
        $input = new ArrayInput([
            'file' => $filePath,
        ]);

        self::expectException(InvalidConfigurationException::class);

        $this->command->run($input, $output);
    }

    public static function getInvalidFiles(): Generator
    {
        yield 'empty file' => [__DIR__ . '/../Fixtures/config/empty.json'];
    }

    public function testCommandWillFailWithoutArgument(): void
    {
        $output = new BufferedOutput();

        self::expectException(RuntimeException::class);

        $this->command->run(new ArrayInput([]), $output);
    }
}
