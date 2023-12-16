<?php

use Mews\Pos\PosInterface;

$templateTitle = 'History Order';

require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order');

$order = [
    'id'              => $ord ? $ord['id'] : '973009309',
    'transactionDate' => new DateTime(), // odeme tarihi
    'page'            => 1, // optional, default: 1
    'pageSize'        => 10, // optional, default: 10
];

$transaction = PosInterface::TX_TYPE_HISTORY;
// History Order
$query = $pos->history($order);

$response = $query->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
