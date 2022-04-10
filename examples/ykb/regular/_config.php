<?php

require '../_payment_config.php';

$baseUrl = $hostUrl.'/ykb/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createPosNetAccount(
    'yapikredi',
    '6706598320',
    'XXXXXX',
    'XXXXXX',
    '67322946',
    '27426',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_NON_SECURE,
    '10,10,10,10,10,10,10,10'
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
