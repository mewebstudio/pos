<?php

use Mews\Pos\Gateways\PayForPos;

$templateTitle = 'History Request';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

require '../../_templates/_header.php';

function createHistoryOrder(string $gatewayClass, array $extraData): array
{
    $order = [];

    if (PayForPos::class === $gatewayClass) {
        $order = [
            // odeme tarihi
            'transaction_date' => $extraData['transaction_date'] ?? new \DateTimeImmutable(),
        ];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $txTime = new \DateTimeImmutable();
        $order  = [
            'page'       => 1,
            'page_size'  => 20,
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    return $order;
}

$order = createHistoryOrder(get_class($pos), []);
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
