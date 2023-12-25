<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\PosInterface;

trait PaymentTestTrait
{
    private function createPaymentOrder(
        string $currency = PosInterface::CURRENCY_TRY,
        int $installment = 0,
        bool $tekrarlanan = false
    ): array {
        $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

        $order = [
            'id'          => $orderId,
            'amount'      => 1.01,
            'currency'    => $currency,
            'installment' => $installment,
            'ip'          => '127.0.0.1',
            'success_url' => 'http:localhost/response.php',
            'fail_url'    => 'http:localhost/response.php',
        ];

        if ($tekrarlanan) {
            // Desteleyen Gatewayler: GarantiPos, EstPos, PayFlexV4

            $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

            $recurringFrequency     = 3;
            $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
            $endPeriod              = $installment * $recurringFrequency;

            $order['recurring'] = [
                'frequency'     => $recurringFrequency,
                'frequencyType' => $recurringFrequencyType,
                'installment'   => $installment,
                'startDate'     => new \DateTimeImmutable(), // GarantiPos optional
                'endDate'       => (new \DateTime())->modify(\sprintf('+%d %s', $endPeriod, $recurringFrequencyType)), // Sadece PayFlexV4'te zorunlu
            ];
        }

        return $order;
    }

    private function createPostPayOrder(PosInterface $pos, array $lastResponse): array
    {
        $postAuth = [
            'id'          => $lastResponse['order_id'],
            'amount'      => $lastResponse['amount'],
            'currency'    => $lastResponse['currency'],
            'ip'          => '127.0.0.1',
        ];

        $gatewayClass = \get_class($pos);
        if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }
        if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
            $postAuth['installment'] = $lastResponse['installment'];
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }

        return $postAuth;
    }

    private function createStatusOrder(PosInterface $pos, array $lastResponse): array
    {
        if ([] === $lastResponse) {
            throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
        }

        $statusOrder = [
            'id'       => $lastResponse['order_id'], // MerchantOrderId
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
        $gatewayClass = get_class($pos);
        if (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
            $statusOrder['remote_order_id'] = $lastResponse['remote_order_id']; // OrderId
        }

        if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
            /**
             * payment_model:
             * siparis olusturulurken kullanilan odeme modeli
             * orderId'yi dogru sekilde formatlamak icin zorunlu.
             */
            $statusOrder['payment_model'] = $lastResponse['payment_model'];
        }

        if (!isset($lastResponse['recurring_id'])) {
            return $statusOrder;
        }

        if (\Mews\Pos\Gateways\EstPos::class === $gatewayClass) {
            // tekrarlanan odemenin durumunu sorgulamak icin:
            return [
                // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
                'recurringId' => $lastResponse['recurring_id'],
            ];
        }

        if (\Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass) {
            // tekrarlanan odemenin durumunu sorgulamak icin:
            return [
                // tekrarlanan odeme sonucunda banktan donen deger: $response['Extra']['RECURRINGID']
                'recurringId' => $lastResponse['recurring_id'],
            ];
        }

        return $statusOrder;
    }

    public function createCancelOrder(PosInterface $pos, array $lastResponse): array
    {
        if ([] === $lastResponse) {
            throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
        }

        $cancelOrder = [
            'id'          => $lastResponse['order_id'], // MerchantOrderId
            'currency'    => $lastResponse['currency'],
            'ref_ret_num' => $lastResponse['ref_ret_num'],
            'ip'          => '127.0.0.1',
        ];

        $gatewayClass = get_class($pos);
        if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
            $cancelOrder['amount'] = $lastResponse['amount'];
        } elseif (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
            $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $cancelOrder['auth_code']       = $lastResponse['auth_code'];
            $cancelOrder['trans_id']        = $lastResponse['trans_id'];
            $cancelOrder['amount']          = $lastResponse['amount'];
        } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
            // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
            $cancelOrder['trans_id'] = $lastResponse['trans_id'];
        } elseif (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
            /**
             * payment_model:
             * siparis olusturulurken kullanilan odeme modeli
             * orderId'yi dogru şekilde formatlamak icin zorunlu.
             */
            $cancelOrder['payment_model'] = $lastResponse['payment_model'];
            // satis islem disinda baska bir islemi (Ön Provizyon İptali, Provizyon Kapama İptali, vs...) iptal edildiginde saglanmasi gerekiyor
            // 'transaction_type' => $lastResponse['transaction_type'],
        }

        if (!isset($lastResponse['recurring_id'])) {
            return $cancelOrder;
        }

        if (\Mews\Pos\Gateways\EstPos::class === $gatewayClass) {
            // tekrarlanan odemeyi iptal etmek icin:
            return [
                'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
            ];
        }

        if (\Mews\Pos\Gateways\EstV3Pos::class === $gatewayClass) {
            // tekrarlanan odemeyi iptal etmek icin:
            return [
                'recurringOrderInstallmentNumber' => 1, // hangi taksidi iptal etmek istiyoruz?
            ];
        }

        return $cancelOrder;
    }
}
