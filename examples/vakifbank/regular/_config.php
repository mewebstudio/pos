<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/vakifbank/regular/';

$merchantId = '000000000111111';
$terminalId = 'VP000095';
$isyeriSifre = '3XTgER89as';

$account = \Mews\Pos\Factory\AccountFactory::createVakifBankAccount(
    'vakifbank',
    $merchantId,
    $isyeriSifre,
    $terminalId,
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
