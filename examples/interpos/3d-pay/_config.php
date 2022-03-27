<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/interpos/3d-pay/';

$userCode =  'InterTestApi';
$userPass = '3';
$shopCode = '3123';
$merchantPass = 'gDg1N';
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    $shopCode,
    $userCode,
    $userPass,
    '3d_pay',
    $merchantPass,
    \Mews\Pos\Gateways\InterPos::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Model Pay Payment';
