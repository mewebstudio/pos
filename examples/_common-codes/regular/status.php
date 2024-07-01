<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Order Status';
// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';
$transaction = PosInterface::TX_TYPE_STATUS;

require '../../_templates/_header.php';

function createStatusOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $statusOrder = [
        'id'       => $lastResponse['order_id'], // MerchantOrderId
        'currency' => $lastResponse['currency'],
        'ip'       => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];
    if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $statusOrder['remote_order_id'] = $lastResponse['remote_order_id']; // OrderId
    }
    if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru sekilde formatlamak icin zorunlu.
         */
        $statusOrder['payment_model'] = $lastResponse['payment_model'];
    }
    if (isset($lastResponse['recurring_id'])
        && (\Mews\Pos\Gateways\EstPos::class === $gatewayClass || \Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass)
    ) {
        // tekrarlanan odemenin durumunu sorgulamak icin:
        $statusOrder = [
            // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
            'recurringId' => $lastResponse['recurring_id'],
        ];
    }

    return $statusOrder;
}

$order = createStatusOrder(get_class($pos), $session->get('last_response'), $ip);
dump($order);

$pos->status($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
