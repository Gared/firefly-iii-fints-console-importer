<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\State;

use Fhp\FinTs;
use Fhp\Options\FinTsOptions;

class StateHandler
{
    private const string STATE_DIRECTORY_PATH = __DIR__ . '/../../data/states/';

    public function persist(FinTs $finTs, FinTsOptions $finTsOptions): void
    {
        $persistedFinTs = $finTs->persist();

        $filePath = $this->getFilePath($finTsOptions->bankCode);

        $writtenBytes = file_put_contents($filePath, $persistedFinTs);
        if ($writtenBytes === false) {
            throw new StatePersistException('Failed to persist state to file: ' . $filePath);
        }
    }

    public function load(string $bankCode): string
    {
        $filePath = $this->getFilePath($bankCode);

        if (file_exists($filePath) === false) {
            throw new StatePersistException('No persisted state found for bank code: ' . $bankCode);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new StatePersistException('Unable to read file: ' . $filePath);
        }

        return $content;
    }

    public function ensureStateDirectoryExists(): void
    {
        if (!is_dir(self::STATE_DIRECTORY_PATH)) {
            if (!mkdir(self::STATE_DIRECTORY_PATH, 0o770, true)) {
                throw new StatePersistException('Directory does not exist: ' . self::STATE_DIRECTORY_PATH);
            }
        }

        if (!is_writable(self::STATE_DIRECTORY_PATH)) {
            throw new StatePersistException('Directory is not writable: ' . self::STATE_DIRECTORY_PATH);
        }
    }

    private function getFilePath(string $bankCode): string
    {
        $fileName = urlencode($bankCode) . '.txt';

        return self::STATE_DIRECTORY_PATH . $fileName;
    }
}
