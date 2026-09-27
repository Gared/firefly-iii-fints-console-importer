<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\Segment\HIUPD\HIUPD;

enum BankAccountType
{
    case CHECKING;
    case SAVINGS;
    case FIXED_TERM_DEPOSIT;
    case SECURITIES_ACCOUNT;
    case LOAN_ACCOUNT;
    case CREDIT_CARD_ACCOUNT;
    case FUND_ACCOUNT;
    case BUILDING_SOCIETY_CONTRACT;
    case INSURANCE_CONTRACT;
    case OTHER;

    private static function fromNumber(int $accountType): self
    {
        return match (true) {
            $accountType >= 1 && $accountType <= 9 => self::CHECKING,
            $accountType >= 10 && $accountType <= 19 => self::SAVINGS,
            $accountType >= 20 && $accountType <= 29 => self::FIXED_TERM_DEPOSIT,
            $accountType >= 30 && $accountType <= 39 => self::SECURITIES_ACCOUNT,
            $accountType >= 40 && $accountType <= 49 => self::LOAN_ACCOUNT,
            $accountType >= 50 && $accountType <= 59 => self::CREDIT_CARD_ACCOUNT,
            $accountType >= 60 && $accountType <= 69 => self::FUND_ACCOUNT,
            $accountType >= 70 && $accountType <= 79 => self::BUILDING_SOCIETY_CONTRACT,
            $accountType >= 80 && $accountType <= 89 => self::INSURANCE_CONTRACT,
            default => self::OTHER,
        };
    }

    public static function fromHiupd(HIUPD $hiupd): ?self
    {
        if ($hiupd->getKontoart() !== null) {
            return self::fromNumber($hiupd->getKontoart());
        }

        if (str_contains($hiupd->getKontoproduktbezeichnung() ?? '', 'Depot')) {
            return self::SECURITIES_ACCOUNT;
        }

        return null;
    }
}
