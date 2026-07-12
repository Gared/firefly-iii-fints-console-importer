<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Console;

use Gared\FireflyImporter\Config\ConfigFileHandlerFactory;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'config:validate')]
class ValidateConfigCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Check if a configuration file is valid')
            ->addArgument('file', InputArgument::REQUIRED, 'The configuration file');
    }

    public function __invoke(InputInterface $input, SymfonyStyle $io): int
    {
        $configFileHandlerFactory = new ConfigFileHandlerFactory();
        $configFileHandler = $configFileHandlerFactory->create();

        $filePath = $input->getArgument('file');
        if (is_string($filePath) === false) {
            throw new InvalidArgumentException('The configuration file must be a string');
        }

        $configFileHandler->load($filePath);

        $io->success('The configuration file is valid!');

        return self::SUCCESS;
    }
}
