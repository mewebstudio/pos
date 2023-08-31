<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Refund Order';
require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY), $session);

// Refund Order
$order = [
    'id'       => $ord['id'],
    'currency' => PosInterface::CURRENCY_TRY,
    'amount'   => $ord['amount'],
];
$transaction = PosInterface::TX_REFUND;

// Refund Order
$pos->refund($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
