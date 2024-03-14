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
            'reqDate'  => $extraData['reqDate'] ?? new \DateTimeImmutable(),
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
