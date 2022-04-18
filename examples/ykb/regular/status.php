<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Order Status';
require '../../template/_header.php';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id' => $ord['id'],
];
$transaction = AbstractGateway::TX_STATUS;
$pos->prepare($order, $transaction);

// Query Order
$pos->status();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
