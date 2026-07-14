<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\BaseAction;
use Fhp\FinTs;
use Fhp\Model\TanMode;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;

class TanModeHandler
{
    public function handle(FinTs $finTs, BaseAction $action, SymfonyStyle $io): void
    {
        if ($action->needsTan() === false) {
            return;
        }

        $selectedTanMode = $finTs->getSelectedTanMode();

        if ($selectedTanMode === null) {
            throw new RuntimeException('No tan mode was selected');
        }

        if ($selectedTanMode->isDecoupled()) {
            $this->handleDecoupled($finTs, $selectedTanMode, $action, $io);
        } else {
            throw new RuntimeException('TODO: Implement coupled tan mode');
        }
    }

    private function handleDecoupled(FinTs $finTs, TanMode $tanMode, BaseAction $action, SymfonyStyle $io): void
    {
        $tanRequest = $action->getTanRequest();
        if ($tanRequest === null) {
            throw new RuntimeException('There is no tan request');
        }

        if ($tanRequest->getChallenge() !== null) {
            $io->info('Instructions: ' . $tanRequest->getChallenge());
        }
        if ($tanRequest->getTanMediumName() !== null) {
            $io->info('Please check this device: ' . $tanRequest->getTanMediumName());
        }

        if ($tanMode->allowsAutomatedPolling()) {
            $io->info('Polling server to detect when the decoupled authentication is complete');
            sleep($tanMode->getFirstDecoupledCheckDelaySeconds());
            for ($attempt = 0;
                $tanMode->getMaxDecoupledChecks() === 0 || $attempt < $tanMode->getMaxDecoupledChecks();
                $attempt++) {
                $io->info('Checking if decoupled authentication is complete...');
                if ($finTs->checkDecoupledSubmission($action)) {
                    return;
                }

                sleep($tanMode->getPeriodicDecoupledCheckDelaySeconds());
            }

            throw new RuntimeException('Decoupled authentication was not completed in time. Please try again.');
        }
    }
}
