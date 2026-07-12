<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\Firefly\Model;

enum AccountType: string
{
    case Asset = 'asset';
    case Expense = 'expense';
    case Import = 'import';
    case Revenue = 'revenue';
    case Cash = 'cash';
    case Liability = 'liability';
    case Liabilities = 'liabilities';
    case InitialBalance = 'initial-balance';
    case Reconciliation = 'reconciliation';
}
