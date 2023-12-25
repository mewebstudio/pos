<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu kapama)';

function createPostPayOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $postAuth = [
        'id'          => $lastResponse['order_id'],
        'amount'      => $lastResponse['amount'],
        'currency'    => $lastResponse['currency'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }
    if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        $postAuth['installment'] = $lastResponse['installment'];
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }

    return $postAuth;
}

$order = createPostPayOrder(get_class($pos), $session->get('last_response'), $ip);
dump($order);


$session->set('post_order', $order);
$transaction = PosInterface::TX_TYPE_PAY_POST_AUTH;
$card = null;

require '../../_templates/_payment_response.php';
