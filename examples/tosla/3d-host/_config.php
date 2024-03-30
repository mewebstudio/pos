<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createToslaPosAccount(
    'tosla',
    '1000000494',
    'POS_ENT_Test_001',
    'POS_ENT_Test_001!*!*',
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
