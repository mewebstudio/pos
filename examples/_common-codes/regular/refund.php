<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Refund Order';

// ilgili bankanin _config.php dosyasi load ediyoruz.
// ornegin /examples/finansbank-payfor/regular/_config.php
require '_config.php';
$transaction = PosInterface::TX_TYPE_REFUND;

require '../../_templates/_header.php';

function createRefundOrder(string $gatewayClass, array $lastResponse, string $ip, ?float $refundAmount = null): array
{
    $refundOrder = [
        'id'           => $lastResponse['order_id'], // MerchantOrderId
        'amount'       => $refundAmount ?? $lastResponse['amount'],

        // toplam siparis tutari, kismi iade mi ya da tam iade mi oldugunu anlamak icin kullanilir.
        'order_amount' => $lastResponse['amount'],

        'currency'     => $lastResponse['currency'],
        'ref_ret_num'  => $lastResponse['ref_ret_num'],
        'ip'           => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
        $refundOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $refundOrder['auth_code']       = $lastResponse['auth_code'];
        $refundOrder['transaction_id']  = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\VakifKatilimPos::class === $gatewayClass) {
        $refundOrder['remote_order_id']  = $lastResponse['remote_order_id']; // banka tarafındaki order id
        // on otorizasyon islemin iadesi icin PosInterface::TX_TYPE_PAY_PRE_AUTH saglanmasi gerekiyor
        $refundOrder['transaction_type'] = $lastResponse['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH;
    } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $refundOrder['transaction_id'] = $lastResponse['transaction_id'];
    } elseif (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
        /**
         * payment_model: siparis olusturulurken kullanilan odeme modeli.
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $refundOrder['payment_model'] = $lastResponse['payment_model'];
    }

    if (isset($lastResponse['recurring_id'])) {
        // tekrarlanan odemeyi iade etmek icin:
        if (\Mews\Pos\Gateways\AkbankPos::class === $gatewayClass) {
            // odemesi gerceklesmis recurring taksidinin iadesi:
            $refundOrder += [
                'recurring_id'                    => $lastResponse['recurring_id'],
                'recurringOrderInstallmentNumber' => 1,
            ];
        }
    }

    return $refundOrder;
}

$lastResponse = $session->get('last_response');
// kismi iade:
$refundAmount = $lastResponse['amount'] - 2;


$order = createRefundOrder(
    get_class($pos),
    $lastResponse,
    $ip,
    $refundAmount
);
dump($order);

try {
    $pos->refund($order);
} catch (Exception $e) {
    dd($e);
}

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
