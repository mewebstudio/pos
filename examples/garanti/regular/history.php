<?php

require '_config.php';
$templateTitle = 'History Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

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
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
