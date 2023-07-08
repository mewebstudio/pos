<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$lastResponse = $session->get('last_response');

// Refund Order
$order        = [
    'id'          => $lastResponse['order_id'],
    'ref_ret_num' => $lastResponse['ref_ret_num'],
    'auth_code'   => $lastResponse['auth_code'],
    'trans_id'    => $lastResponse['trans_id'],
    'amount'      => $lastResponse['amount'],
    'currency'    => $lastResponse['currency'],
];
$transaction = AbstractGateway::TX_REFUND;
$pos->prepare($order, $transaction);

$pos->refund();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
