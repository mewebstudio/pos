<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Cancel Order';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$order = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $session);

$order = [
    'id' => $order['id'], //ReferenceTransactionId
    'ip' => $order['ip'],
];
$transaction = AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();

require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
