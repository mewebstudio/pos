<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$merchantId = '000000000111111';
$terminalId = 'VP000095';
$isyeriSifre = '3XTgER89as';
//account bilgileri kendi account bilgilerinizle degistiriniz
$account = \Mews\Pos\Factory\AccountFactory::createPayFlexAccount(
    'vakifbank',
    $merchantId,
    $isyeriSifre,
    $terminalId,
    PosInterface::MODEL_NON_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
