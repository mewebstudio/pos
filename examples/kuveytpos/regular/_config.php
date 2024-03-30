<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    '496',
    'apiuser1',
    '400235',
    'Api1232',
    PosInterface::MODEL_3D_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_3D_SECURE;
