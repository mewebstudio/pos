<?php

use \Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$hostMerchantId = '000100000013506';
$hostTerminalId = 'VP000579';
$merchantPassword  = '123456';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayFlexAccount(
    'vakifbank-cp',
    $hostMerchantId,
    $merchantPassword,
    $hostTerminalId,
    PosInterface::MODEL_NON_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
