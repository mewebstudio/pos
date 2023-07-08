<?php

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    '496',
    'apiuser1',
    '400235',
    'Api1232',
    \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE
);

$pos = getGateway($account);

$templateTitle = 'Regular Payment';
