<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    '6702640212',
    '',
    '',
    '67C16990',
    '1010062861356072',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
