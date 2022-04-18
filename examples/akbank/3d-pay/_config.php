<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank',
    '700655000200',
    'ISBANKAPI',
    'ISBANK07',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_PAY,
    'TRPS0200',
    \Mews\Pos\Gateways\EstPos::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Pay Model Payment';
