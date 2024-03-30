<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount(
    'qnbfinansbank-payfor',
    '085300000009704',
    'QNB_API_KULLANICI_3DPAY',
    'UcBN0',
    PosInterface::MODEL_3D_HOST,
    '12345678'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
