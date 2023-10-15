<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Order Status';
require '_config.php';
require '../../_templates/_header.php';

function createStatusOrder(PosInterface $pos, \Symfony\Component\HttpFoundation\Session\SessionInterface $session, string $ip): array
{
    $lastResponse = $session->get('last_response');

    if (!$lastResponse) {
        throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
    }

    $statusOrder = [
        'id'       => $lastResponse['order_id'], // MerchantOrderId
        'currency' => $lastResponse['currency'] ?? PosInterface::CURRENCY_TRY,
        'ip'       => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];
    if (get_class($pos) === \Mews\Pos\Gateways\KuveytPos::class) {
        $statusOrder['remote_order_id'] = $lastResponse['remote_order_id']; // OrderId
    }
    if (get_class($pos) === \Mews\Pos\Gateways\PosNetV1Pos::class || get_class($pos) === \Mews\Pos\Gateways\PosNet::class) {
        /**
         * payment_model:
         * siparis olusturulurken kullanilan odeme modeli
         * orderId'yi dogru sekilde formatlamak icin zorunlu.
         */
        $statusOrder['payment_model'] = $lastResponse['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
    }
    if (isset($lastResponse['recurring_id'])
        && get_class($pos) === \Mews\Pos\Gateways\EstPos::class || get_class($pos) === \Mews\Pos\Gateways\EstV3Pos::class
    ) {
        // tekrarlanan odemenin durumunu sorgulamak icin:
        $statusOrder = [
            // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
            'recurringId' => $lastResponse['recurring_id'],
        ];
    }

    return $statusOrder;
}

$order = createStatusOrder($pos, $session, $ip);
dump($order);

$transaction = PosInterface::TX_STATUS;

$pos->status($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
