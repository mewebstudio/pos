<?php

require '_config.php';
$templateTitle = 'History Order';
require '../../template/_header.php';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
    'ip'       => $ord['ip'],
];
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_HISTORY;
$pos->prepare($order, $transaction);

// History Order
$query = $pos->history([]);

$response = $query->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
