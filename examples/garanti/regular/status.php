<?php

require '_config.php';
$templateTitle = 'Order Status';
require '../../template/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
    'ip'       => $ord['ip'],
];
$transaction = AbstractGateway::TX_STATUS;
$pos->prepare($order, $transaction);

$pos->status();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
