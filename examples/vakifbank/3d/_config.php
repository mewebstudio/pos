<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/vakifbank/3d/';

$successUrl = $failUrl = $baseUrl.'response.php';

$merchantId = '000000000111111';
$terminalId = 'VP000095';
$isyeriSifre = '3XTgER89as';
$account = \Mews\Pos\Factory\AccountFactory::createVakifBankAccount(
    'vakifbank',
    $merchantId,
    $isyeriSifre,
    $terminalId,
    '3d'
);

$pos = getGateway($account);

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_PAY;

$templateTitle = '3D Model Payment';
