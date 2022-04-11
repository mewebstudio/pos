<?php

$templateTitle = 'History Order';

require '_config.php';
require '../../template/_header.php';

$ord = $session->get('order');

$order = [
    //siparis tarihi
    //'reqDate'  => '20201031',
    //veya siparis ID
    'order_id' => $ord ? $ord['id'] : '20201031C06E',
];

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_HISTORY;
// History Order
$query = $pos->history($order);

$response = $query->getResponse();

require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
