<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Functional;

use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;

trait PaymentTestTrait
{
    private function createPaymentOrder(
        string $currency = PosInterface::CURRENCY_TRY,
        float  $amount = 1.01,
        int    $installment = 0,
        bool   $tekrarlanan = false
    ): array {
        $orderId = date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 4));

        $order = [
            'id'          => $orderId,
            'amount'      => $amount,
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

    private function createPostPayOrder(string $gatewayClass, array $lastResponse): array
    {
        $postAuth = [
            'id'       => $lastResponse['order_id'],
            'amount'   => $lastResponse['amount'],
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];

        if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }

        if (\Mews\Pos\Gateways\PosNetV1Pos::class === $gatewayClass || \Mews\Pos\Gateways\PosNet::class === $gatewayClass) {
            $postAuth['installment'] = $lastResponse['installment_count'];
            $postAuth['ref_ret_num'] = $lastResponse['ref_ret_num'];
        }

        return $postAuth;
    }

    private function createStatusOrder(string $gatewayClass, array $lastResponse): array
    {
        if ([] === $lastResponse) {
            throw new \LogicException('ödeme verisi bulunamadı, önce ödeme yapınız');
        }

        $statusOrder = [
            'id'       => $lastResponse['order_id'], // MerchantOrderId
            'currency' => $lastResponse['currency'],
            'ip'       => '127.0.0.1',
        ];
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

    public function createCancelOrder(string $gatewayClass, array $lastResponse): array
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

        if (\Mews\Pos\Gateways\GarantiPos::class === $gatewayClass) {
            $cancelOrder['amount'] = $lastResponse['amount'];
        } elseif (\Mews\Pos\Gateways\KuveytPos::class === $gatewayClass) {
            $cancelOrder['remote_order_id'] = $lastResponse['remote_order_id']; // banka tarafındaki order id
            $cancelOrder['auth_code']       = $lastResponse['auth_code'];
            $cancelOrder['transaction_id']  = $lastResponse['transaction_id'];
            $cancelOrder['amount']          = $lastResponse['amount'];
        } elseif (\Mews\Pos\Gateways\PayFlexV4Pos::class === $gatewayClass || \Mews\Pos\Gateways\PayFlexCPV4Pos::class === $gatewayClass) {
            // çalışmazsa $lastResponse['all']['ReferenceTransactionId']; ile denenmesi gerekiyor.
            $cancelOrder['transaction_id'] = $lastResponse['transaction_id'];
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

    private function createOrderHistoryOrder(string $gatewayClass, array $lastResponse): array
    {
        $order = [];
        if (EstPos::class === $gatewayClass || EstV3Pos::class === $gatewayClass) {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        } elseif (ToslaPos::class === $gatewayClass) {
            $order = [
                'id'               => $lastResponse['order_id'],
                'transaction_date' => $lastResponse['transaction_time'], // odeme tarihi
                'page'             => 1, // optional, default: 1
                'page_size'        => 10, // optional, default: 10
            ];
        } elseif (PayForPos::class === $gatewayClass) {
            $order = [
                'id' => $lastResponse['order_id'],
            ];
        } elseif (GarantiPos::class === $gatewayClass) {
            $order = [
                'id'       => $lastResponse['order_id'],
                'currency' => $lastResponse['currency'],
                'ip'       => '127.0.0.1',
            ];
        }

        return $order;
    }


    private function createHistoryOrder(string $gatewayClass, array $extraData): array
    {
        $order = [];

        if (PayForPos::class === $gatewayClass) {
            $order = [
                // odeme tarihi
                'transaction_date' => $extraData['transaction_date'] ?? new \DateTimeImmutable(),
            ];
        }

        return $order;
    }

    private function createRefundOrder(string $gatewayClass, array $lastResponse): array
    {
        $refundOrder = [
            'id'          => $lastResponse['order_id'], // MerchantOrderId
            'amount'      => $lastResponse['amount'],
            'currency'    => $lastResponse['currency'],
            'ref_ret_num' => $lastResponse['ref_ret_num'],
            'ip'          => '127.0.0.1',
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
}
