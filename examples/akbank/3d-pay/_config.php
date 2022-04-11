<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank',
    'XXXXXXX',
    'XXXXXXX',
    '',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY,
    'XXXXXXX'
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Pay Model Payment';
