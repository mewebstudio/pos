<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank',
    'XXXXXXX',
    'XXXXXXX',
    'XXXXXXX',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE,
    'XXXXXXX',
    \Mews\Pos\Gateways\EstPos::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Model Payment';
