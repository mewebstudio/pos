<?php

use Mews\Pos\PosInterface;

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', PosInterface::CURRENCY_TRY), $session);

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
    'payment_model' => PosInterface::MODEL_3D_SECURE,
];

/*
// faster params...
$order = [
    'id'      => '201810295863',
    'ref_ret_num'  => '018711539490000181',
    'auth_code'     => '115394',
];
*/
$transaction = PosInterface::TX_CANCEL;

// Cancel Order
$pos->cancel($order);

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
