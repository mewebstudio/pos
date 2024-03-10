<?php

use Mews\Pos\Gateways\AkOdePos;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\PayForPos;

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

$templateTitle = 'History Order';

function createHistoryOrder(string $gatewayClass, array $lastResponse, array $extraData): array
{
    $order = [];
    if (EstPos::class === $gatewayClass || EstV3Pos::class === $gatewayClass) {
        $order = [
            'id' => $lastResponse['order_id'],
        ];
    }

    if (AkOdePos::class === $gatewayClass) {
        $order = [
            'id'              => $lastResponse['order_id'],
            'transactionDate' => $lastResponse['transaction_time'], // odeme tarihi
            'page'            => 1, // optional, default: 1
            'pageSize'        => 10, // optional, default: 10
        ];
    }
    if (PayForPos::class === $gatewayClass) {
        if (isset($extraData['reqDate'])) {
            $order = [
                // odeme tarihi
                'reqDate'  => $extraData['reqDate'],
            ];
        } else {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        }
    }

    if (GarantiPos::class === $gatewayClass) {
        $order = [
            'id'       => $lastResponse['order_id'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
    }

    return $order;
}

$lastResponse = $session->get('last_response');

$order = createHistoryOrder(get_class($pos), $lastResponse, [], $ip);
dump($order);

$transaction = \Mews\Pos\PosInterface::TX_TYPE_HISTORY;

try {
    $pos->history($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
