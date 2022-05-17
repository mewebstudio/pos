<?php

use Mews\Pos\Gateways\AbstractGateway;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

//$userCode ve $userPass 3d-host odemede kullanilmiyor.
$userCode =  '';
$userPass = '';
$shopCode = '3123';
$merchantPass = 'gDg1N';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    $shopCode,
    $userCode,
    $userPass,
    AbstractGateway::MODEL_3D_HOST,
    $merchantPass,
    AbstractGateway::LANG_TR
);

$pos = getGateway($account);

$transaction = AbstractGateway::TX_PAY;

$templateTitle = '3D Host Model Payment';
