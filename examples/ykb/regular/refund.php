<?php

use Mews\Pos\Gateways\AbstractGateway;

require '_config.php';
$templateTitle = 'Refund Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$transaction = AbstractGateway::TX_REFUND;
$pos->prepare([
    'id'            => $ord['id'],
    /**
     * payment_model:
     * siparis olusturulurken kullanilan odeme modeli
     * orderId'yi dogru sekilde formatlamak icin zorunlu.
     */
    'payment_model' => AbstractGateway::MODEL_3D_SECURE,
    'amount'        => $ord['amount'],
    'currency'      => $ord['currency'],
], $transaction);

// Refund Order
$pos->refund();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
