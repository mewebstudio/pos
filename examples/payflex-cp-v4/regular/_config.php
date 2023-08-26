<?php

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
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
$paymentModel = \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE;
