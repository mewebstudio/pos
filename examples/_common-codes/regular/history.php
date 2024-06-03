<?php

$templateTitle = 'History Request';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';

require '../../_templates/_header.php';

function createHistoryOrder(string $gatewayClass, array $extraData, string $ip): array
{
    $order  = [];
    $txTime = new \DateTimeImmutable();
    if (\Mews\Pos\Gateways\PayForPos::class === $gatewayClass) {
        $order = [
            // odeme tarihi
            'transaction_date' => $extraData['transaction_date'] ?? $txTime,
        ];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $order  = [
            'page'       => 1,
            'page_size'  => 20,
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        $order = [
            'ip'         => $ip,
            'currency'   => \Mews\Pos\PosInterface::CURRENCY_USD,
            'page'       => 1, //optional
            // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
            'start_date' => $txTime,
            'end_date'   => $txTime->modify('+1 day'),
        ];
    } elseif (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
        $order  = [
            // Gün aralığı 1 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];
//        ya da batch number ile (batch number odeme isleminden alinan response'da bulunur):
//        $order  = [
//            'batch_num' => 24,
//        ];
    }

    return $order;
}

$order = createHistoryOrder(get_class($pos), [], $ip);
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
