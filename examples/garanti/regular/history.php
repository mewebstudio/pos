<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'History Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY), $session);

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
    'ip'       => $ord['ip'],
];
$transaction = PosInterface::TX_HISTORY;

// History Order
$query = $pos->history($order);

$response = $query->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
