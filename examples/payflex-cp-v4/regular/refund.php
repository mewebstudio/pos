<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$order = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);
// Refund Order
$order = [
    'id'     => $order['id'], //ReferenceTransactionId
    'amount' => $order['amount'],
    'ip'     => $order['ip'],
];
$transaction = AbstractGateway::TX_REFUND;

$pos->refund($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
