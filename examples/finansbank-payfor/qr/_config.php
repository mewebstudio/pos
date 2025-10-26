<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/qr/';
//account bilgileri kendi account bilgilerinizle degistiriniz

$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount(
    'qnbfinansbank-payfor',
    '007200000000711',
    'QR_TEST',
    'IGhq8',
    PosInterface::MODEL_3D_HOST,
    '88921532',
    PosInterface::LANG_TR,
    \Mews\Pos\Entity\Account\PayForAccount::MBR_ID_FINANSBANK // ya da PayForAccount::MBR_ID_ZIRAAT_KATILIM
);

$pos = getGateway($account, $eventDispatcher);

$transaction = $session->get('tx', PosInterface::TX_TYPE_PAY_AUTH);

$templateTitle = 'QR Code Payment';
$paymentModel  = PosInterface::MODEL_3D_HOST;
