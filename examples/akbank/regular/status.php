<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Order Status';
require '_config.php';
require '../../_templates/_header.php';

$ord = $session->get('order');
$lastResponse = $session->get('last_response');

if (isset($ord['recurringFrequency']) && $lastResponse && $lastResponse->recurring_id) {
    //tekrarlanan odemenin durumunu sorgulamak icin:
    $order = [
        // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
        'recurringId' => $lastResponse->recurring_id,
    ];

} else {
    $order = [
        'id' => $ord ? $ord['id'] : '973009309',
    ];
}

$transaction = PosInterface::TX_STATUS;

// Query Order
$pos->status($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
