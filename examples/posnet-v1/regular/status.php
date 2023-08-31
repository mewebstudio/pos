<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Order Status';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY), $session);

$order = [
    'id' => $ord['id'],
    /**
     * payment_model:
     * siparis olusturulurken kullanilan odeme modeli
     * orderId'yi dogru sekilde formatlamak icin zorunlu.
     */
    'payment_model' => PosInterface::MODEL_3D_SECURE,
];
$transaction = PosInterface::TX_STATUS;

// Query Order
$pos->status($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
