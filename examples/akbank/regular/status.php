<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Order Status';
require '_config.php';
require '../../template/_header.php';

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

$transaction = AbstractGateway::TX_STATUS;
$pos->prepare($order, $transaction);
// Query Order
$pos->status();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
