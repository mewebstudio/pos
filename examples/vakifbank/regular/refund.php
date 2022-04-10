<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Refund Order';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $session);
// Refund Order
$order = [
    'id'     => $order['id'], //ReferenceTransactionId
    'amount' => $order['amount'],
    'ip'     => $order['ip'],
];
$transaction = AbstractGateway::TX_REFUND;
$pos->prepare($order, $transaction);

$pos->refund();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
