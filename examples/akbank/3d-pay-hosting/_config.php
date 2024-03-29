<?php

use Mews\Pos\Gateways\AbstractGateway;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay-hosting/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'isbank',
    '700655000200',
    'ISBANKAPI',
    'ISBANK07',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY_HOSTING,
    'TRPS0200',
    AbstractGateway::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Pay Hosting Model Payment';
