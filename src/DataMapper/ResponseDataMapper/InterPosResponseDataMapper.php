<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\PosInterface;

class InterPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        '81'                         => 'invalid_credentials',
        'E31'                        => 'invalid_transaction',
        'E39'                        => 'invalid_transaction',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        if ([] === $rawPaymentResponseData) {
            return $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $status                 = self::TX_DECLINED;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);

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
        $result['currency']         = $order['currency'];
        $result['amount']           = $order['amount'];

        $this->logger->debug('mapped payment response', $result);

        return $result;
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
        $status              = $raw3DAuthResponseData['mdStatus'];
        $procReturnCode      = $this->getProcReturnCode($raw3DAuthResponseData);
        $paymentResponseData = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_SECURE);
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->map3DPaymentResponse($rawPaymentResponseData, $txType, PosInterface::MODEL_3D_SECURE);
        }

        $threeDResponse = [
            'order_id'             => $paymentResponseData['order_id'] ?? $raw3DAuthResponseData['OrderId'],
            'proc_return_code'     => $paymentResponseData['proc_return_code'] ?? $procReturnCode,
            'ref_ret_num'          => $paymentResponseData['ref_ret_num'] ?? $raw3DAuthResponseData['HostRefNum'],
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['mdStatus']),
            'payment_model'        => PosInterface::MODEL_3D_SECURE,
            'md_status'            => $status,
            'masked_number'        => $raw3DAuthResponseData['Pan'],
            'month'                => null,
            'year'                 => null,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['PurchAmount']),
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
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $result = $this->map3DPaymentData($raw3DAuthResponseData, $raw3DAuthResponseData, $txType, $order);

        $result['payment_model'] = PosInterface::MODEL_3D_PAY;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $result = $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);

        $result['payment_model'] = PosInterface::MODEL_3D_HOST;

        return $result;
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
            'refund_amount'    => $this->formatAmount($rawResponseData['RefundedAmount']),
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
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ProcReturnCode'] ?? null;
    }

    /**
     * 0 => 0.0
     * 1.056,2 => 1056.2
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return (float) \str_replace(',', '.', str_replace('.', '', $amount));
    }

    /**
     * @phpstan-param PosInterface::TX_*    $txType
     * @phpstan-param PosInterface::MODEL_* $paymentModel
     *
     * @param array<string, mixed>|null $rawPaymentResponseData
     * @param string                    $txType
     * @param string                    $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3DPaymentResponse(?array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $this->logger->debug('mapping 3d payment response', [$rawPaymentResponseData]);
        if (null === $rawPaymentResponseData || [] === $rawPaymentResponseData) {
            return $this->getDefaultPaymentResponse($txType, $paymentModel);
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $status                 = self::TX_DECLINED;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result = $this->getDefaultPaymentResponse($txType, $paymentModel);

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

        $this->logger->debug('mapped payment response', $result);

        return $result;
    }
}
