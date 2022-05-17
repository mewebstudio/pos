<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createGarantiPosAccount(
    'garanti',
    '7000679',
    'PROVAUT',
    '123qweASD/',
    '30691298',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE,
    '',
    'PROVRFN',
    '123qweASD/'
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
