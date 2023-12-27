<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    '496',
    'apiuser1',
    '400235',
    'Api1232',
    PosInterface::MODEL_3D_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Model Payment';
$paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
