<?php

$templateTitle = 'History Request';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require_once '_config.php';
$transaction = \Mews\Pos\PosInterface::TX_TYPE_HISTORY;

require '../../_templates/_header.php';

function createHistoryOrder(string $gatewayClass, array $extraData, string $ip): array
{
    $txTime = new \DateTimeImmutable();
    if (\Mews\Pos\Gateways\PayForPos::class === $gatewayClass) {
        return [
            // odeme tarihi
            'transaction_date' => $extraData['transaction_date'] ?? $txTime,
        ];
    }

    if (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        return [
            'page'       => 1,
            'page_size'  => 20,
            /**
             * Tarih aralığı maksimum 90 gün olabilir.
             */
            'start_date' => $txTime->modify('-1 day'),
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
        return [
            'ip'         => $ip,
            'currency'   => \Mews\Pos\PosInterface::CURRENCY_USD,
            'page'       => 1, //optional
            // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
            'start_date' => $txTime,
            'end_date'   => $txTime->modify('+1 day'),
        ];
    }

    if (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
        return [
            // Gün aralığı 1 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,
        ];
//        ya da batch number ile (batch number odeme isleminden alinan response'da bulunur):
//        return [
//            'batch_num' => 396,
//        ];
    }

    if (\Mews\Pos\Gateways\ParamPos::class === $gatewayClass) {
        return [
            // Gün aralığı 7 günden fazla girilemez
            'start_date' => $txTime->modify('-23 hour'),
            'end_date'   => $txTime,

            // optional:
            // Bu değerler gönderilince API nedense hata veriyor.
//            'transaction_type' => \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH, // TX_TYPE_CANCEL, TX_TYPE_REFUND
//            'order_status' => 'Başarılı', // Başarılı, Başarısız
        ];
    }

    return [];
}

$order = createHistoryOrder(get_class($pos), [], $ip);
dump($order);

try {
    $pos->history($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
