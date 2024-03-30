<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    '7000679',
    'PROVAUT',
    '123qweASD/',
    '30691298',
    PosInterface::MODEL_NON_SECURE,
    null,
    'PROVRFN',
    '123qweASD/'
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
