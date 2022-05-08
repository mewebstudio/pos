<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    '496',
    'apiuser1',
    '400235',
    'Api1232',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Model Payment';
