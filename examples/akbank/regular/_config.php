<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/akbank/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createEstPosAccount(
    'akbank',
    'XXXXXXX',
    'XXXXXXX',
    '',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
