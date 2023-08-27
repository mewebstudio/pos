<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    '6702640212', // 10 haneli üye işyeri numarası
    '67C16990', // 8 haneli üye işyeri terminal numarası
    '1010062861356072', // 16 haneli üye işyeri EPOS numarası.
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
$paymentModel = \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE;
