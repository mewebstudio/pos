<?php

use Mews\Pos\PosInterface;

require '../_payment_config.php';

$baseUrl = $bankTestsUrl.'/regular/';

$account = \Mews\Pos\Factory\AccountFactory::createKuveytPosAccount(
    'vakif-katilim',
    '1',
    'APIUSER',
    '11111',
    'kdsnsksl',
);

$pos = getGateway($account, $eventDispatcher);

$templateTitle = 'Regular Payment';
$paymentModel = PosInterface::MODEL_NON_SECURE;
