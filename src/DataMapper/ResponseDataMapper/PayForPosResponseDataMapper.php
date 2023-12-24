<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\PosInterface;

class PayForPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,

        '96'   => 'general_error',
        'V004' => 'invalid_credentials',
        'V001' => 'invalid_credentials',
        'V111' => 'general_error',
        'V013' => 'reject',
        'V014' => 'request_rejected',
        'V015' => 'request_rejected',
        'V025' => 'general_error',
        'V029' => 'general_error',
        'V034' => 'try_again',
        'V036' => 'general_error',
        'M025' => 'general_error',
        'M042' => 'general_error',
        'M002' => 'invalid_transaction',
        'M012' => 'invalid_transaction',
        'MR15' => 'try_again',
        'M041' => 'reject',
        'M049' => 'invalid_credentials',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $defaultPaymentResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultPaymentResponse;
        }
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['TransId'],
            'trans_id'         => $rawPaymentResponseData['TransId'],
            'auth_code'        => $rawPaymentResponseData['AuthCode'],
            'currency'         => $order['currency'],
            'amount'           => $order['amount'],
            'ref_ret_num'      => $rawPaymentResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => (self::TX_DECLINED === $status) ? $procReturnCode : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawPaymentResponseData['ErrMsg'] : null,
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultPaymentResponse, $mappedResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $procReturnCode      = $this->getProcReturnCode($raw3DAuthResponseData);
        $threeDAuthStatus    = ('1' === $raw3DAuthResponseData['3DStatus']) ? self::TX_APPROVED : self::TX_DECLINED;
        $paymentResponseData = [];

        $txType = isset($rawPaymentResponseData['TxnType']) ? $txType : ($this->mapTxType($raw3DAuthResponseData['TxnType']) ?? $txType);

        if (self::TX_APPROVED === $threeDAuthStatus && null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->map3DPaymentResponseCommon($rawPaymentResponseData, $txType, PosInterface::MODEL_3D_SECURE);
        }

        $threeDResponse = [
            'trans_id'         => null,
            'auth_code'        => $raw3DAuthResponseData['AuthCode'],
            'ref_ret_num'      => $raw3DAuthResponseData['HostRefNum'],
            'transaction_type' => $txType,
            'order_id'         => $raw3DAuthResponseData['OrderId'],
            'proc_return_code' => $procReturnCode,
            'status'           => self::TX_DECLINED,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED !== $threeDAuthStatus ? $procReturnCode : null,
            'error_message'    => self::TX_APPROVED !== $threeDAuthStatus ? $raw3DAuthResponseData['ErrMsg'] : null,
        ];

        if ([] === $paymentResponseData) {
            $result = $this->mergeArraysPreferNonNullValues(
                $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_SECURE),
                $threeDResponse,
            );

            return $this->mergeArraysPreferNonNullValues(
                $result,
                $this->map3DCommonResponseData($raw3DAuthResponseData)
            );
        }

        $result = $this->mergeArraysPreferNonNullValues($threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData));

        return $this->mergeArraysPreferNonNullValues($result, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        $status                = self::PROCEDURE_SUCCESS_CODE === $procReturnCode ? self::TX_APPROVED : self::TX_DECLINED;
        $threeDResponse        = [
            'trans_id'         => null,
            'auth_code'        => $raw3DAuthResponseData['AuthCode'],
            'ref_ret_num'      => $raw3DAuthResponseData['HostRefNum'],
            'order_id'         => $raw3DAuthResponseData['OrderId'],
            'transaction_type' => $this->mapTxType($raw3DAuthResponseData['TxnType']),
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => (self::TX_APPROVED !== $status) ? $procReturnCode : null,
            'error_message'    => (self::TX_APPROVED !== $status) ? $raw3DAuthResponseData['ErrMsg'] : null,
        ];

        return $this->mergeArraysPreferNonNullValues(
            $threeDResponse,
            $this->map3DCommonResponseData($raw3DAuthResponseData)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);
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
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['TransId'] ?? null,
            'auth_code'        => (self::TX_DECLINED !== $status) ? $rawResponseData['AuthCode'] : null,
            'ref_ret_num'      => $rawResponseData['HostRefNum'] ?? null,
            'proc_return_code' => $procReturnCode ?? null,
            'trans_id'         => $rawResponseData['TransId'] ?? null,
            'error_code'       => (self::TX_DECLINED === $status) ? $procReturnCode : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawResponseData['ErrMsg'] : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        //status of the requested order
        $orderStatus = null;
        if (self::TX_APPROVED === $status && empty($rawResponseData['AuthCode'])) {
            $orderStatus = self::TX_DECLINED;
        } elseif (self::TX_APPROVED === $status && !empty($rawResponseData['AuthCode'])) {
            $orderStatus = self::TX_APPROVED;
        }

        return [
            'auth_code'        => $rawResponseData['AuthCode'] ?? null,
            'order_id'         => $rawResponseData['OrderId'] ?? null,
            'org_order_id'     => $rawResponseData['OrgOrderId'] ?? null,
            'proc_return_code' => $procReturnCode,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawResponseData['ErrMsg'] : null,
            'ref_ret_num'      => $rawResponseData['HostRefNum'] ?? null,
            'order_status'     => $orderStatus,
            'transaction_type' => null === $rawResponseData['TxnType'] ? null : $this->mapTxType($rawResponseData['TxnType']),
            'masked_number'    => $rawResponseData['CardMask'] ?? null,
            'amount'           => null !== $rawResponseData['PurchAmount'] ? $this->formatAmount($rawResponseData['PurchAmount']) : null,
            'currency'         => $this->mapCurrency($rawResponseData['Currency']),
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
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
     * returns mapped data of the common response data among all 3d models.
     *
     * @param array<string, string> $raw3DAuthResponseData
     *
     * @return array<string, string|float|string|null>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData): array
    {
        $procReturnCode   = $this->getProcReturnCode($raw3DAuthResponseData);
        $threeDAuthStatus = ('1' === $raw3DAuthResponseData['3DStatus']) ? self::TX_APPROVED : self::TX_DECLINED;

        return [
            'transaction_security' => $raw3DAuthResponseData['SecureType'],
            'masked_number'        => $raw3DAuthResponseData['CardMask'],
            'amount'               => $this->formatAmount($raw3DAuthResponseData['PurchAmount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['Currency']),
            'tx_status'            => $raw3DAuthResponseData['TxnResult'],
            'md_status'            => $raw3DAuthResponseData['3DStatus'],
            'md_error_code'        => (self::TX_DECLINED === $threeDAuthStatus) ? $procReturnCode : null,
            'md_error_message'     => (self::TX_DECLINED === $threeDAuthStatus) ? $raw3DAuthResponseData['ErrMsg'] : null,
            'md_status_detail'     => $this->getStatusDetail($procReturnCode),
            'eci'                  => $raw3DAuthResponseData['Eci'],
            '3d_all'               => $raw3DAuthResponseData, //todo this should be empty for 3dpay and 3dhost payments
        ];
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
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
     * @param array<string, mixed> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ProcReturnCode'] ?? null;
    }

    /**
     * @phpstan-param PosInterface::TX_*       $txType
     * @phpstan-param PosInterface::MODEL_3D_* $paymentModel
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param string               $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3DPaymentResponseCommon(array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $defaultPaymentResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if ([] === $rawPaymentResponseData) {
            return $defaultPaymentResponse;
        }
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['TransId'],
            'trans_id'         => $rawPaymentResponseData['TransId'],
            'auth_code'        => $rawPaymentResponseData['AuthCode'],
            'ref_ret_num'      => $rawPaymentResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => (self::TX_DECLINED === $status) ? $procReturnCode : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawPaymentResponseData['ErrMsg'] : null,
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped 3d payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultPaymentResponse, $mappedResponse);
    }
}
