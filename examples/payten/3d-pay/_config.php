<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'payten_v3_hash',
    '700655000200',
    'ISBANKAPI',
    'ISBANK07',
    PosInterface::MODEL_3D_PAY,
    'TRPS0200',
    PosInterface::LANG_TR
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
