<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Refund Order';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';

require '../../_templates/_header.php';

function createRefundOrder(string $gatewayClass, array $lastResponse, string $ip): array
{
    $refundOrder = [
        'id'          => $lastResponse['order_id'], // MerchantOrderId
        'amount'      => $lastResponse['amount'],
        'currency'    => $lastResponse['currency'],
        'ref_ret_num' => $lastResponse['ref_ret_num'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $refundOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $refundOrder['auth_code']       = $lastResponse['auth_code'];
        $refundOrder['transaction_id']  = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $refundOrder['transaction_id'] = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model:
         * siparis olusturulurken kullanilan odeme modeli
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $refundOrder['payment_model'] = $lastResponse['payment_model'];
    }

    return $refundOrder;
}


$order = createRefundOrder(get_class($pos), $session->get('last_response'), $ip);
dump($order);

$transaction = PosInterface::TX_TYPE_REFUND;

try {
    $pos->refund($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
