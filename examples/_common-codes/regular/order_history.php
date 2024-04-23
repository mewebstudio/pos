<?php

use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\ToslaPos;

$templateTitle = 'Order History';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

require '../../_templates/_header.php';


function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
{
    $order = [];
    if (EstPos::class === $gatewayClass || EstV3Pos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (ToslaPos::class === $gatewayClass) {
        $order = [
            'id'               => $lastResponse['order_id'],
            'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
            'page'             => 1, // optional, default: 1
            'page_size'        => 10, // optional, default: 10
        ];
    } elseif (PayForPos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (GarantiPos::class === $gatewayClass) {
        $order = [
            'id'       => $lastResponse['order_id'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        /** @var DateTimeImmutable $txTime */
        $txTime = $lastResponse['transaction_time'];
        $order  = [
            'auth_code'  => $lastResponse['auth_code'],
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    return $order;
}

$lastResponse = $session->get('last_response');

$order = createOrderHistoryOrder(get_class($pos), $lastResponse);
dump($order);

$transaction = \Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY;

try {
    $pos->orderHistory($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
