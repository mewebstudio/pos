<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';

$userCode =  'InterTestApi';
$userPass = '3';
$shopCode = '3123';
$merchantPass = 'gDg1N';
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    $shopCode,
    $userCode,
    $userPass,
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE,
    $merchantPass,
    \Mews\Pos\Gateways\InterPos::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Model Payment';
