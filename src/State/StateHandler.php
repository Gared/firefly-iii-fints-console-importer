<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\State;

use Fhp\FinTs;
use Fhp\Options\FinTsOptions;

class StateHandler
{
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

    private function getFilePath(string $bankCode): string
    {
        $fileName = urlencode($bankCode) . '.txt';

        return __DIR__ . '/../../data/states/' . $fileName;
    }
}
