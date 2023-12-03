<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

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
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        if ([] === $rawPaymentResponseData) {
            return $this->getDefaultPaymentResponse();
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED
        ) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => null,
            'trans_id'         => null,
            'auth_code'        => $rawPaymentResponseData['AuthCode'] ?? null,
            'ref_ret_num'      => $rawPaymentResponseData['ReferenceCode'] ?? null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $status !== self::TX_APPROVED ? $procReturnCode : null,
            'error_message'    => $status !== self::TX_APPROVED ? $rawPaymentResponseData['ServiceResponseData']['ResponseDescription'] : null,
            'all'              => $rawPaymentResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $mdStatus            = $raw3DAuthResponseData['MdStatus'];
        $transactionSecurity = $this->mapResponseTransactionSecurity($mdStatus);


        $threeDResponse = [
            'order_id'             => $raw3DAuthResponseData['OrderId'] ?? null,
            'transaction_security' => $transactionSecurity,
            'masked_number'        => $raw3DAuthResponseData['CCPrefix'], // Kredi Kartı Numarası ön eki: 450634
            'proc_return_code'     => null,
            'status'               => self::TX_DECLINED,
            'md_status'            => $mdStatus,
            'md_error_message'     => '1' !== $mdStatus ? $raw3DAuthResponseData['MdErrorMessage'] : null,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['Amount']),
            '3d_all'               => $raw3DAuthResponseData,
        ];

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData ?? []);

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData($raw3DAuthResponseData): array
    {
        return $this->map3DPaymentData($raw3DAuthResponseData, $raw3DAuthResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData): array
    {
        return $this->map3DPayResponseData($raw3DAuthResponseData);
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
            'trans_id'         => null,
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

        return [
            'auth_code'        => null,
            'trans_id'         => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'    => self::TX_APPROVED === $status ? null : $rawResponseData['ServiceResponseData']['ResponseDescription'],
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        return $this->emptyStringsToNull($rawResponseData);
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
}
