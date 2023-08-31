<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$order = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY), $session);
// Refund Order
$order = [
    'id'     => $order['id'], //ReferenceTransactionId
    'amount' => $order['amount'],
    'ip'     => $order['ip'],
];
$transaction = PosInterface::TX_REFUND;

$pos->refund($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
