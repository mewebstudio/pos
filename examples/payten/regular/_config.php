<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'payten_v3_hash',
    '700655000200',
    'ISBANKAPI',
    'ISBANK07',
    PosInterface::MODEL_NON_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
