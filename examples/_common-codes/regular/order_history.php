<?php

$templateTitle = 'Order History';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';
$transaction = \Mews\Pos\PosInterface::TX_TYPE_ORDER_HISTORY;

require '../../_templates/_header.php';


function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
{
    $order = [];
    if (\Mews\Pos\Gateways\EstPos::class === $gatewayClass || \Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
        if (isset($lastResponse['recurring_id'])) {
            $order = [
                'recurring_id' => $lastResponse['recurring_id'],
            ];
        } else {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        }
    } elseif (\Mews\Pos\Gateways\ToslaPos::class === $gatewayClass) {
        $order = [
            'id'               => $lastResponse['order_id'],
            'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
            'page'             => 1, // optional, default: 1
            'page_size'        => 10, // optional, default: 10
        ];
    } elseif (\Mews\Pos\Gateways\PayForPos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    } elseif (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $order = [
            'id'       => $lastResponse['order_id'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        /** @var \DateTimeImmutable $txTime */
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

try {
    $pos->orderHistory($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
