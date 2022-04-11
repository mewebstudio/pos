<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-host/';

//$userCode ve $userPass 3d-host odemede kullanilmiyor.
$userCode =  '';
$userPass = '';
$shopCode = '3123';
$merchantPass = 'gDg1N';

$account = \Mews\Pos\Factory\AccountFactory::createInterPosAccount(
    'denizbank',
    $shopCode,
    $userCode,
    $userPass,
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_HOST,
    $merchantPass,
    \Mews\Pos\Gateways\InterPos::LANG_TR
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Host Model Payment';
