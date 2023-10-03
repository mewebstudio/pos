<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay-hosting/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbankv3',
    '700655000200',
    'ISBANKAPI',
    'ISBANK07',
    PosInterface::MODEL_3D_PAY_HOSTING,
    'TRPS0200',
    PosInterface::LANG_TR
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_PAY;

$templateTitle = '3D Pay Hosting Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY_HOSTING;
