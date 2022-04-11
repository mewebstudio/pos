<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank',
    'XXXXXXX',
    'XXXXXXX',
    '',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_HOST,
    'XXXXXXX'
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Host Model Payment';
