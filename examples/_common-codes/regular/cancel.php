<?php

use Mews\Pos\PosInterface;

$templateTitle = 'Refund Order';
require '_config.php';
require '../../_templates/_header.php';

function createCancelOrder(PosInterface $pos, \Symfony\Component\HttpFoundation\Session\SessionInterface $session, string $ip): array
{
    $lastResponse = $session->get('last_response');

    if (!$lastResponse) {
        throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
    }

    $cancelOrder = [
        'id'          => $lastResponse['order_id'], // MerchantOrderId
        'currency'    => $lastResponse['currency'] ?? PosInterface::CURRENCY_TRY,
        'ref_ret_num' => $lastResponse['ref_ret_num'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (get_class($pos) === \Mews\Pos\Gateways\GarantiPos::class) {
        $cancelOrder['email']  = '';
        $cancelOrder['amount'] = $lastResponse['amount'];
    } elseif (get_class($pos) === \Mews\Pos\Gateways\KuveytPos::class) {
        $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
        $cancelOrder['auth_code']       = $lastResponse['auth_code'];
        $cancelOrder['trans_id']        = $lastResponse['trans_id'];
        $cancelOrder['amount']          = $lastResponse['amount'];
    } elseif (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class || get_class($pos) === \Mews\Pos\Gateways\PayFlexCPV4Pos::class) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $cancelOrder['trans_id'] = $lastResponse['trans_id'];
    } elseif (get_class($pos) === \Mews\Pos\Gateways\PosNetV1Pos::class || get_class($pos) === \Mews\Pos\Gateways\PosNet::class) {
        /**
         * payment_model:
         * siparis olusturulurken kullanilan odeme modeli
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $cancelOrder['payment_model'] = $lastResponse['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
        // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
        // 'transaction_type' => $lastResponse['transaction_type'],
    }


    if (isset($lastResponse['recurring_id'])
        && get_class($pos) === \Mews\Pos\Gateways\EstPos::class || get_class($pos) === \Mews\Pos\Gateways\EstV3Pos::class
    ) {
        // tekrarlanan odemeyi iptal etmek icin:
        $cancelOrder = [
            'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
        ];
    }

    return $cancelOrder;

    $refundOrder = [
        'id'          => $lastResponse['order_id'], // MerchantOrderId
        'amount'      => $lastResponse['amount'],
        'currency'    => $lastResponse['currency'] ?? PosInterface::CURRENCY_TRY,
        'ref_ret_num' => $lastResponse['ref_ret_num'],
        'ip'          => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1',
    ];

    if (get_class($pos) === \Mews\Pos\Gateways\GarantiPos::class) {
        $refundOrder['email']       = '';
    } elseif (get_class($pos) === \Mews\Pos\Gateways\KuveytPos::class) {
        $refundOrder['remote_order_id'] = $lastResponse['remote_order_id'];
        $refundOrder['auth_code']       = $lastResponse['auth_code'];
        $refundOrder['trans_id']        = $lastResponse['trans_id'];
    } elseif (get_class($pos) === \Mews\Pos\Gateways\PayFlexV4Pos::class || get_class($pos) === \Mews\Pos\Gateways\PayFlexCPV4Pos::class) {
        // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
        $refundOrder['trans_id'] = $lastResponse['trans_id'];
    } elseif (get_class($pos) === \Mews\Pos\Gateways\PosNetV1Pos::class || get_class($pos) === \Mews\Pos\Gateways\PosNet::class) {
        /**
         * payment_model:
         * siparis olusturulurken kullanilan odeme modeli
         * orderId'yi dogru şekilde formatlamak icin zorunlu.
         */
        $refundOrder['payment_model'] = $lastResponse['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
    }

    return $refundOrder;
}


$order = createCancelOrder($pos, $session, $ip);
dump($order);

$transaction = PosInterface::TX_CANCEL;

$pos->cancel($order);

$response = $pos->getResponse();

require '../../_templates/_simple_response_dump.php';
require '../../_templates/_footer.php';
