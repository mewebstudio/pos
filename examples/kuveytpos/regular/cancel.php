<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$lastResponse = $session->get('last_response');

$order        = [
    'id'          => $lastResponse['order_id'],
    'ref_ret_num' => $lastResponse['ref_ret_num'],
    'auth_code'   => $lastResponse['auth_code'],
    'trans_id'    => $lastResponse['trans_id'],
    'amount'      => $lastResponse['amount'],
    'currency'    => $lastResponse['currency'],
];

/*$order        = [
    'id'          => '2023070849CD',
    'ref_ret_num' => '318923298433',
    'auth_code'   => '241839',
    'trans_id'    => '298433',
    'amount'      => 1.01,
    'currency'    => 'TRY',
];*/

$transaction = AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
