<?php

declare(strict_types=1);

namespace Gared\FireflyImporter\FinTS;

use Fhp\Action\GetStatementOfAccount;
use Fhp\Action\GetStatementOfAccountXML;
use Fhp\CAMT\CAMT;
use Fhp\FinTs;
use Fhp\Model\SEPAAccount;
use Fhp\Model\StatementOfAccount\StatementOfAccount;
use Fhp\UnsupportedException;
use Gared\FireflyImporter\Config\Parser\Config;

class AccountStatementLoader
{
    public function getStatementOfAccount(SEPAAccount $account, Config $config, FinTs $finTs): StatementOfAccount
    {
        try {
            $getStatementOfAccountRequestXML = GetStatementOfAccountXML::create(
                account: $account,
                from: $config->account->fromDate,
                to: $config->account->toDate,
            );
            $finTs->execute($getStatementOfAccountRequestXML);
            $bookedXML = $getStatementOfAccountRequestXML->getBookedXML();

            $parser = new CAMT();
            $parsedCAMT = $parser->parse($bookedXML);

            return StatementOfAccount::fromCAMTArray($parsedCAMT);
        } catch (UnsupportedException) {
            $getStatementOfAccountRequest = GetStatementOfAccount::create(
                account: $account,
                from: $config->account->fromDate,
                to: $config->account->toDate,
            );
            $finTs->execute($getStatementOfAccountRequest);

            return $getStatementOfAccountRequest->getStatement();
        }
    }
}
