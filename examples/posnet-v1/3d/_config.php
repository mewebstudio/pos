<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'albaraka',
    '6702640212', // 10 haneli üye işyeri numarası
    '67C16990', // 8 haneli üye işyeri terminal numarası
    '1010062861356072', // 16 haneli üye işyeri EPOS numarası.
    PosInterface::MODEL_3D_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
