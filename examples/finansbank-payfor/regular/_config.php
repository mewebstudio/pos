<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayForAccount(
    'qnbfinansbank-payfor',
    '085300000009704',
    'QNB_API_KULLANICI_3DPAY',
    'UcBN0'
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
