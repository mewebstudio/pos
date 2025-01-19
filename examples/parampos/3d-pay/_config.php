<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    10738,
    'Test',
    'Test',
    '0c13d406-873b-403b-9c09-a5766840d98c'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
