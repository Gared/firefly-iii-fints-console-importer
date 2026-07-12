<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

enum StructuredDescriptionCodes: string
{
    case PurposeCode = 'PURP';
    case Mandatsnummer = 'KREF';
    case Mandatsreferenznummer = 'MREF';
    case CreditorIdentifier = 'CRED';
}
