<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createParamPosAccount(
    'param-pos',
    10738, // CLIENT_CODE Terminal ID
    'Test', // CLIENT_USERNAME Kullanıcı adı
    'Test', // CLIENT_PASSWORD Şifre
    '0c13d406-873b-403b-9c09-a5766840d98c' // GUID Üye İşyeri ait anahtarı
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
