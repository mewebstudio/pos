<?php

use Mews\Pos\PosInterface;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';

$templateTitle = 'Post Auth Order (ön provizyonu kapama)';

function createPostPayOrder(string $gatewayClass, array $lastResponse, string $ip, ?float $postAuthAmount = null): array
{
    $postAuth = [
        'id'              => $lastResponse['order_id'],
        'amount'          => $postAuthAmount ?? $lastResponse['amount'],
        'pre_auth_amount' => $lastResponse['amount'], // amount > pre_auth_amount durumlar icin kullanilir
        'currency'        => $lastResponse['currency'],
        'ip'              => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }
    if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        $postAuth['installment'] = $lastResponse['installment_count'];
        $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
    }

    return $postAuth;
}

$lastResponse = $session->get('last_response');

$preAuthAmount = $lastResponse['amount'];
// otorizasyon kapama amount'u ön otorizasyon amount'tan daha fazla olabilir.
$postAuthAmount = $lastResponse['amount'] + 0.20;
$gatewayClass = get_class($pos);

$order = createPostPayOrder(
    $gatewayClass,
    $lastResponse,
    $ip,
    $postAuthAmount
);

dump($order);

$transaction = PosInterface::TX_TYPE_PAY_POST_AUTH;

require '../../_templates/_finish_non_secure_post_auth_payment.php';
