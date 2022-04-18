<?php

$templateTitle = 'Cancel Order';
require '_config.php';
require '../../template/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
];

$transaction = AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();

require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
