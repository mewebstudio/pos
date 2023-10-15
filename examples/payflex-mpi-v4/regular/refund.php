<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$order = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY));
// Refund Order
$order = [
    'id' => $session->get('ref_ret_num'), //ReferenceTransactionId
    'ip' => $request->getClientIp(),
    'amount' => $order['amount'],
];
$transaction = PosInterface::TX_REFUND;

$pos->refund($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
