<?php

require '_config.php';
$templateTitle = 'Cancel Order';
require '../../_templates/_header.php';

$ord = $session->get('order') ?: getNewOrder($baseUrl, $ip, $request->get('currency', 'TRY'), $session);

$order = [
    // order id veya ref_ret_num (ReferenceCode) saglanmasi gerekiyor. Ikisinden biri zorunlu.
    // daha iyi performance icin ref_ret_num tercih edilmelidir.
    'id' => $ord['id'],
    'ref_ret_num' => $session->get('ref_ret_num'),

    /**
     * payment_model:
     * cancel islemi orderId ile yapiliyorsa zorunlu.
     * cancel islemi ref_ret_num ile yapiliyorsa zorunlu degil.
     *
     * siparis olusturulurken kullanilan odeme modeli
     * orderId'yi dogru sekilde formatlamak icin zorunlu.
     */
    'payment_model' => \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE,

    // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
    // 'transaction_type' => \Mews\Pos\Gateways\AbstractGateway::TX_PRE_PAY,
];

$transaction = \Mews\Pos\Gateways\AbstractGateway::TX_CANCEL;
$pos->prepare($order, $transaction);

// Cancel Order
$pos->cancel();

$response = $pos->getResponse();
require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
