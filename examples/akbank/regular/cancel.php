<?php

use Mews\Pos\Gateways\AbstractGateway;

$templateTitle = 'Cancel Order';
require '_config.php';
require '../../template/_header.php';

$ord = $session->get('order') ? $session->get('order') : getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

if (isset($ord['recurringFrequency'])) {
    //tekrarlanan odemenin durumunu sorgulamak icin:
    $order = [
        // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
        'id' => $ord['id'],
        //hangi taksidi iptal etmek istiyoruz:
        'recurringOrderInstallmentNumber' => $ord['recurringInstallmentCount'],
    ];
    // Not: bu islem sadece bekleyen odemeyi iptal eder
} else {
    $order = [
        'id' => $ord['id'],
    ];
}

$transaction = AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

$pos->cancel();

$response = $pos->getResponse();
require '../../template/_simple_response_dump.php';
require '../../template/_footer.php';
