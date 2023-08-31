<?php

$templateTitle = 'Order Status';
require '_config.php';
require '../../_templates/_header.php';

use Mews\Pos\PosInterface;

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id' => $ord['id'],
];
$transaction = PosInterface::TX_STATUS;

$pos->status($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
