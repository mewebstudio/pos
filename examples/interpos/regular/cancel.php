<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY));

$order = [
    'id'       => $ord['id'],
    'currency' => $ord['currency'],
];
$transaction = PosInterface::TX_CANCEL;

// Cancel Order
$pos->cancel($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
