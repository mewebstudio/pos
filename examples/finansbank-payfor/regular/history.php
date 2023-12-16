<?php

use Mews\Pos\PosInterface;

$templateTitle = 'History Order';

require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order');

$order = [
    //siparis tarihi
    //'reqDate'  => '20201031',
    //veya siparis ID
    'orderId' => $ord ? $ord['id'] : '20201031C06E',
];

$transaction = PosInterface::TX_TYPE_HISTORY;
// History Order
$query = $pos->history($order);

$response = $query->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
