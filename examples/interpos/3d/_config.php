<?php

use Mews\Pos\Gateways\AbstractGateway;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$userCode =  'InterTestApi';
$userPass = '3';
$shopCode = '3123';
$merchantPass = 'gDg1N';
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    $shopCode,
    $userCode,
    $userPass,
    AbstractGateway::MODEL_3D_SECURE,
    $merchantPass,
    AbstractGateway::LANG_TR
);

$pos = getGateway($account);

$transaction = AbstractGateway::TX_PAY;

$templateTitle = '3D Model Payment';
