<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Order Status';
require '_config.php';
require '../../template/_header.php';
require '../_header.php';

$ord = $session->get('order');

$order = [
    'id' => $ord ? $ord['id'] : '973009309',
];
$transaction = AbstractGateway::TX_STATUS;
$pos->prepare($order, $transaction);

$pos->status();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
