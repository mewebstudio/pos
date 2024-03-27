<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d-pay/';

$hostMerchantId = '000100000013506';
$hostTerminalId = 'VP000579';
$merchantPassword  = '123456';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayFlexAccount(
    'vakifbank-cp',
    $hostMerchantId,
    $merchantPassword,
    $hostTerminalId,
    PosInterface::MODEL_3D_PAY
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Pay Model Payment';
$paymentModel = PosInterface::MODEL_3D_PAY;
