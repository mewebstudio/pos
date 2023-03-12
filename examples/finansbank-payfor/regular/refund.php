<?php

$templateTitle = 'Refund Order';
require '_config.php';
require '../../_templates/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

// Refund Order
$order = [
    'id'       => $ord['id'],
    'amount'   => $ord['amount'],
    'currency' => $ord['currency'],
];
$transaction = AbstractGateway::TX_REFUND;
$pos->prepare($order, $transaction);

$pos->refund();

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
