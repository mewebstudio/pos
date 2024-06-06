<?php

use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/3d/';
// NOT: PosNet testleri lokalde yapilamiyor.
//      Ortam farketmeksizin Yapikrediyle iletisime gecip, sunucu IP adresinize izin verilmesini sağlamanız gerekiyor.

//account bilgileri kendi account bilgilerinizle degistiriniz
$account = AccountFactory::createPosNetAccount(
    'yapikredi',
    '6706598320',
    '67322946',
    '27426',
    PosInterface::MODEL_3D_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = '3D Model Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
