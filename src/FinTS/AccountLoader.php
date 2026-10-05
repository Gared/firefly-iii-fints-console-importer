<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Exception;
use Fhp\Action\GetSEPAAccounts;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Protocol\UPD;
use Fhp\Segment\HIUPD\HIUPDv6;
use Gared\FireflyImporter\Config\Parser\Config;
use RuntimeException;

class AccountLoader
{
    public function load(FinTs $finTs, UPD $upd, Config $config): SEPAAccount
    {
        try {
            return $this->getSepaAccount($finTs, $config);
        } catch (Exception) {
        }

        foreach ($upd->hiupd as $accountSegment) {
            $accountInfo = $accountSegment->getKontoverbindung();
            if ($accountInfo === null) {
                continue;
            }

            if (
                ($accountSegment instanceof HIUPDv6 && $config->account->iban === $accountSegment->iban)
                || $config->account->iban === $accountInfo->getAccountNumber()
            ) {
                $account = new SEPAAccount();
                $account
                    ->setBlz($accountInfo->getBankIdentifier())
                    ->setAccountNumber($accountInfo->getAccountNumber())
                    ->setSubAccount($accountInfo->unterkontomerkmal ?? null);

                if ($accountSegment instanceof HIUPDv6) {
                    $account->setIban($accountSegment->iban);
                }

                return $account;
            }
        }

        throw new RuntimeException('No SEPA account matching the provided IBAN or account number');
    }

    private function getSepaAccount(FinTs $finTs, Config $config): SEPAAccount
    {
        $getSepaAccountsAction = GetSEPAAccounts::create();
        $finTs->execute($getSepaAccountsAction);
        $accounts = $getSepaAccountsAction->getAccounts();

        foreach ($accounts as $account) {
            if ($account->getIban() === $config->account->iban) {
                return $account;
            }
        }

        throw new RuntimeException('Account not found. Please review your configuration file');
    }
}
