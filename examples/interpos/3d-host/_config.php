<?php

use Mews\Pos\PosInterface;

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
    PosInterface::MODEL_3D_HOST,
    $merchantPass,
    PosInterface::LANG_TR
);

$pos = getGateway($account, $eventDispatcher);

$transaction = PosInterface::TX_TYPE_PAY_AUTH;

$templateTitle = '3D Host Model Payment';
$paymentModel = PosInterface::MODEL_3D_HOST;
