<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

class PosNetV1PosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '00';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        '0'                          => 'declined',
        '2'                          => 'declined',
        '0001'                       => 'bank_call',
        '0005'                       => 'reject',
        '0007'                       => 'bank_call',
        '0012'                       => 'reject',
        '0014'                       => 'reject',
        '0030'                       => 'bank_call',
        '0041'                       => 'reject',
        '0043'                       => 'reject',
        '0051'                       => 'reject',
        '0053'                       => 'bank_call',
        '0054'                       => 'reject',
        '0057'                       => 'reject',
        '0058'                       => 'reject',
        '0062'                       => 'reject',
        '0065'                       => 'reject',
        '0091'                       => 'bank_call',
        '0123'                       => 'transaction_not_found',
        '0444'                       => 'bank_call',
    ];

    /**
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string>  $txTypeMappings
     * @param array<PosInterface::MODEL_*, string>    $secureTypeMappings
     * @param LoggerInterface                         $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, array $secureTypeMappings, LoggerInterface $logger)
    {
        parent::__construct($currencyMappings, $txTypeMappings, $secureTypeMappings, $logger);

        $this->currencyMappings += [
            '949' => PosInterface::CURRENCY_TRY,
            '840' => PosInterface::CURRENCY_USD,
            '978' => PosInterface::CURRENCY_EUR,
            '826' => PosInterface::CURRENCY_GBP,
            '392' => PosInterface::CURRENCY_JPY,
            '643' => PosInterface::CURRENCY_RUB,
        ];
    }

    /**
     * Order Status Codes
     *
     * @var array<string, string>
     */
    protected array $orderStatusMappings = [
        PosInterface::TX_TYPE_PAY_AUTH => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        PosInterface::TX_TYPE_CANCEL   => PosInterface::PAYMENT_STATUS_CANCELED,
        PosInterface::TX_TYPE_REFUND   => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED
        ) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $order['id'],
            'currency'         => $order['currency'],
            'amount'           => $order['amount'],
            'transaction_id'   => null,
            'auth_code'        => $rawPaymentResponseData['AuthCode'] ?? null,
            'ref_ret_num'      => $rawPaymentResponseData['ReferenceCode'] ?? null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $status !== self::TX_APPROVED ? $procReturnCode : null,
            'error_message'    => $status !== self::TX_APPROVED ? $rawPaymentResponseData['ServiceResponseData']['ResponseDescription'] : null,
            'all'              => $rawPaymentResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $mappedResponse['installment_count'] = $this->mapInstallment($rawPaymentResponseData['InstallmentData']['InstallmentCount']);
            $mappedResponse['transaction_time']  = new \DateTimeImmutable();
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $mdStatus            = $this->extractMdStatus($raw3DAuthResponseData);
        $threeDAuthApproved  = $this->is3dAuthSuccess($mdStatus);
        $transactionSecurity = null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus);
        /** @var PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType */
        $txType = $this->mapTxType($raw3DAuthResponseData['TranType']) ?? $txType;

        $threeDResponse = [
            'order_id'             => $order['id'],
            'remote_order_id'      => $raw3DAuthResponseData['OrderId'] ?? null,
            'transaction_security' => $transactionSecurity,
            'masked_number'        => $raw3DAuthResponseData['CCPrefix'], // Kredi Kartı Numarası ön eki: 450634
            'proc_return_code'     => null,
            'currency'             => isset($raw3DAuthResponseData['CurrencyCode']) ? $this->mapCurrency($raw3DAuthResponseData['CurrencyCode']) : null,
            'status'               => self::TX_DECLINED,
            'md_status'            => $mdStatus,
            'md_error_message'     => $threeDAuthApproved ? null : $raw3DAuthResponseData['MdErrorMessage'],
            'amount'               => $this->formatAmount($raw3DAuthResponseData['Amount']),
            '3d_all'               => $raw3DAuthResponseData,
        ];

        $paymentResponseData = $this->map3DPaymentResponseCommon($rawPaymentResponseData ?? [], $txType, PosInterface::MODEL_3D_SECURE);

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse($rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'auth_code'        => null,
            'transaction_id'         => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'transaction_type' => null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED !== $status ? $procReturnCode : null,
            'error_message'    => self::TX_APPROVED !== $status ? $rawResponseData['ServiceResponseData']['ResponseDescription'] : null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        if ('0000' === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);
        $defaultResponse['error_code']       = self::TX_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['error_message']    = self::TX_APPROVED === $status ? null : $rawResponseData['ServiceResponseData']['ResponseDescription'];

        if (self::TX_APPROVED === $status && isset($rawResponseData['TransactionData'])) {
            $rawTx = null;
            foreach ($rawResponseData['TransactionData'] as $item) {
                /**
                 * İptal Edilen ve Finansallaştırma işlemleri “1” dönmektedir.
                 * Diğer tüm işlemler “0” dönmektedir.
                 */
                if ('1' === $item['TransactionStatus']) {
                    $rawTx = $item;
                    break;
                }
            }

            if (null === $rawTx) {
                return $defaultResponse;
            }

            $defaultResponse['first_amount']     = $this->formatStatusAmount($rawTx['Amount']);
            $defaultResponse['transaction_time'] = new \DateTimeImmutable($rawTx['TransactionDate']);
            $defaultResponse['currency']         = $this->mapCurrency($rawTx['CurrencyCode']);
            $defaultResponse['masked_number']    = $rawTx['CardNo'];
            $defaultResponse['order_id']         = $rawTx['OrderId'];
            $defaultResponse['transaction_type'] = $this->mapTxType($rawTx['TransactionType']);
            $defaultResponse['order_status']     = $this->orderStatusMappings[$defaultResponse['transaction_type']] ?? null;

            if (PosInterface::TX_TYPE_REFUND === $defaultResponse['transaction_type']) {
                $defaultResponse['refund_time'] = $defaultResponse['transaction_time'];
            } elseif (PosInterface::TX_TYPE_CANCEL === $defaultResponse['transaction_type']) {
                $defaultResponse['cancel_time'] = $defaultResponse['transaction_time'];
            }
        }

        return $defaultResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     *
     * MdStatus degerleri:
     *   0: Kart doğrulama başarısız, işleme devam etmeyin
     *   1: Doğrulama başarılı, işleme devam edebilirsiniz
     *   2: Kart sahibi veya bankası sisteme kayıtlı değil
     *   3: Kartın bankası sisteme kayıtlı değil
     *   4: Doğrulama denemesi, kart sahibi sisteme daha sonra kayıt olmayı seçmiş
     *   5: Doğrulama yapılamıyor
     *   6: 3D Secure hatası
     *   7: Sistem hatası
     *   8: Bilinmeyen kart no
     *   9: Üye İşyeri 3D-Secure sistemine kayıtlı değil (bankada işyeri ve terminal numarası 3d olarak tanımlı değil.)
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return $mdStatus === '1';
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['MdStatus'] ?? null;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        /**
         *   1: Doğrulama başarılı, işleme devam edebilirsiniz
         *   2: Kart sahibi veya bankası sisteme kayıtlı değil
         *   3: Kartın bankası sisteme kayıtlı değil
         *   4: Doğrulama denemesi, kart sahibi sisteme daha sonra kayıt olmayı seçmiş
         */
        $transactionSecurity = 'MPI fallback';
        if ('1' === $mdStatus) {
            $transactionSecurity = 'Full 3D Secure';
        } elseif (in_array($mdStatus, ['2', '3', '4'])) {
            $transactionSecurity = 'Half 3D Secure';
        }

        return $transactionSecurity;
    }

    /**
     * Get Status Detail Text
     *
     * @param string|null $procReturnCode
     *
     * @return string|null
     */
    protected function getStatusDetail(?string $procReturnCode): ?string
    {
        return $this->codes[$procReturnCode] ?? null;
    }

    /**
     * Get ProcReturnCode
     *
     * @param array<string, array<string, string>> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ServiceResponseData']['ResponseCode'] ?? null;
    }

    /**
     * "100001" => 1000.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((int) $amount) / 100;
    }

    /**
     * "1,16" => 1.16
     * @param string $amount
     *
     * @return float
     */
    protected function formatStatusAmount(string $amount): float
    {
        return (float) \str_replace(',', '.', \str_replace('.', '', $amount));
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param string               $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3DPaymentResponseCommon(array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED
        ) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'transaction_id'         => null,
            'auth_code'        => $rawPaymentResponseData['AuthCode'] ?? null,
            'ref_ret_num'      => $rawPaymentResponseData['ReferenceCode'] ?? null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $status !== self::TX_APPROVED ? $procReturnCode : null,
            'error_message'    => $status !== self::TX_APPROVED ? $rawPaymentResponseData['ServiceResponseData']['ResponseDescription'] : null,
            'all'              => $rawPaymentResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $mappedResponse['installment_count'] = $this->mapInstallment($rawPaymentResponseData['InstallmentData']['InstallmentCount']);
            $mappedResponse['transaction_time']  = new \DateTimeImmutable();
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }
}
