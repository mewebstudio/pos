<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'kuveytpos',
    '496',
    'apitest',
    '400235',
    'api123',
    PosInterface::MODEL_NON_SECURE
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
