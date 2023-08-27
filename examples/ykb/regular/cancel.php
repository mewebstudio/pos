<?php

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    'id' => $ord['id'],
    /**
     * payment_model:
     * cancel islemi orderId ile yapiliyorsa zorunlu.
     * cancel islemi ref_ret_num ile yapiliyorsa zorunlu degil.
     *
     * siparis olusturulurken kullanilan odeme modeli
     * orderId'yi dogru sekilde formatlamak icin zorunlu.
     */
    'payment_model' => \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE,
];

/*
// faster params...
$order = [
    'id'      => '201810295863',
    'ref_ret_num'  => '018711539490000181',
    'auth_code'     => '115394',
];
*/
$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
