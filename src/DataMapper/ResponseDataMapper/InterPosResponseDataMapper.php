<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Psr\Log\LogLevel;

class InterPosResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        '81'                         => 'bank_call',
        'E31'                        => 'invalid_transaction',
        'E39'                        => 'invalid_transaction',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$rawPaymentResponseData]);
        if (empty($rawPaymentResponseData)) {
            return $this->getDefaultPaymentResponse();
        }
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $status                 = self::TX_DECLINED;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result = $this->getDefaultPaymentResponse();

        $result['proc_return_code'] = $procReturnCode;
        $result['status']           = $status;
        $result['status_detail']    = $this->getStatusDetail($procReturnCode);
        $result['all']              = $rawPaymentResponseData;
        $result['order_id']         = $rawPaymentResponseData['OrderId'];
        $result['trans_id']         = $rawPaymentResponseData['TransId'];
        $result['auth_code']        = $rawPaymentResponseData['AuthCode'];
        $result['ref_ret_num']      = $rawPaymentResponseData['HostRefNum'];
        $result['error_code']       = $rawPaymentResponseData['ErrorCode'];
        $result['error_message']    = $rawPaymentResponseData['ErrorMessage'];

        $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $status              = $raw3DAuthResponseData['mdStatus'];
        $procReturnCode      = $this->getProcReturnCode($raw3DAuthResponseData);
        $paymentResponseData = $this->getDefaultPaymentResponse();
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'order_id'             => $paymentResponseData['order_id'] ?? $raw3DAuthResponseData['OrderId'],
            'proc_return_code'     => $paymentResponseData['proc_return_code'] ?? $procReturnCode,
            'ref_ret_num'          => $paymentResponseData['ref_ret_num'] ?? $raw3DAuthResponseData['HostRefNum'],
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['mdStatus']),
            'md_status'            => $status,
            'masked_number'        => $raw3DAuthResponseData['Pan'],
            'month'                => null,
            'year'                 => null,
            'amount'               => self::amountFormat($raw3DAuthResponseData['PurchAmount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['Currency']),
            'eci'                  => $raw3DAuthResponseData['Eci'],
            'tx_status'            => $raw3DAuthResponseData['TxnStat'],
            'cavv'                 => null,
            'md_error_message'     => $raw3DAuthResponseData['ErrorMessage'],
            'error_code'           => $paymentResponseData['error_code'] ?? $raw3DAuthResponseData['ErrorCode'],
            'error_message'        => $paymentResponseData['error_message'] ?? $raw3DAuthResponseData['ErrorMessage'],
            'status_detail'        => $paymentResponseData['status_detail'] ?? $this->getStatusDetail($procReturnCode),
            '3d_all'               => $raw3DAuthResponseData,
        ];

        return array_merge($paymentResponseData, $threeDResponse);
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
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['ErrorCode'],
            'error_message'    => $rawResponseData['ErrorMessage'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
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
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['ErrorCode'],
            'error_message'    => $rawResponseData['ErrorMessage'],
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
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_message'    => $rawResponseData['ErrorMessage'],
            'ref_ret_num'      => null,
            'order_status'     => null, //todo success cevap alindiginda eklenecek
            'refund_amount'    => self::amountFormat($rawResponseData['RefundedAmount']),
            'capture_amount'   => null, //todo success cevap alindiginda eklenecek
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'capture'          => null, //todo success cevap alindiginda eklenecek
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
     * 0 => 0.0
     * 1.056,2 => 1056.2
     * @param string $amount
     *
     * @return float
     */
    public static function amountFormat(string $amount): float
    {
        return (float) str_replace(',', '.', str_replace('.', '', $amount));
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
        return $procReturnCode ? ($this->codes[$procReturnCode] ?? null) : null;
    }

    /**
     * Get ProcReturnCode
     *
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ProcReturnCode'] ?? null;
    }
}
