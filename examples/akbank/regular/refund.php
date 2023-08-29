<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Refund Order';
require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

// Refund Order
$order = [
    'id'       => $ord['id'],
    'currency' => 'TRY',
    'amount'   => $ord['amount'],
];
$transaction = AbstractGateway::TX_REFUND;

// Refund Order
$pos->refund($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
