<?php

require '_config.php';
$templateTitle = 'Order Status';
require '../../_templates/_header.php';

use Mews\Pos\Gateways\AbstractGateway;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
    'ip'       => $ord['ip'],
];
$transaction = AbstractGateway::TX_STATUS;

$pos->status($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
