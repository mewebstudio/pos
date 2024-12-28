<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
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
        $result['order_id']         = $rawPaymentResponseData['OrderId'];
        $result['transaction_id']   = $rawPaymentResponseData['TransId'];
        $result['auth_code']        = $rawPaymentResponseData['AuthCode'];
        $result['error_code']       = $rawPaymentResponseData['ErrorCode'];
        $result['error_message']    = $rawPaymentResponseData['ErrorMessage'];
        $result['currency']         = $order['currency'];
        $result['amount']           = $order['amount'];
        $result['all']              = $rawPaymentResponseData;

        if (self::TX_APPROVED === $status) {
            $result['transaction_time'] = new \DateTimeImmutable($rawPaymentResponseData['TRXDATE'] ?? null);
        }

        $this->logger->debug('mapped payment response', $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        return $this->map3DCommonResponseData(
            $raw3DAuthResponseData,
            $rawPaymentResponseData,
            $txType,
            PosInterface::MODEL_3D_SECURE
        );
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->map3DCommonResponseData(
            $raw3DAuthResponseData,
            $raw3DAuthResponseData,
            $txType,
            PosInterface::MODEL_3D_PAY
        );
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->map3DCommonResponseData(
            $raw3DAuthResponseData,
            $raw3DAuthResponseData,
            $txType,
            PosInterface::MODEL_3D_HOST
        );
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
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => null,
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => $rawResponseData['TransId'],
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

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);
        $defaultResponse['order_id']         = $rawResponseData['OrderId'];
        $defaultResponse['transaction_id']   = $rawResponseData['TransId'];
        $defaultResponse['error_code']       = self::TX_APPROVED !== $status ? $procReturnCode : null;
        $defaultResponse['error_message']    = self::TX_APPROVED !== $status ? $rawResponseData['ErrorMessage'] : null;
        $defaultResponse['refund_amount']    = $rawResponseData['RefundedAmount'] > 0 ? $this->formatAmount($rawResponseData['RefundedAmount']) : null;

        // todo success cevap ornegi bulundugunda guncellenecek:
        $defaultResponse['order_status']   = null;
        $defaultResponse['capture_amount'] = null;
        $defaultResponse['capture']        = null;

        if ('' !== $rawResponseData['VoidDate'] && '1.1.0001 00:00:00' !== $rawResponseData['VoidDate']) {
            $defaultResponse['cancel_time'] = new \DateTimeImmutable($rawResponseData['VoidDate']);
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
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return \in_array($mdStatus, ['1', '2', '3', '4'], true);
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['3DStatus'] ?? null;
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
        } elseif (\in_array($mdStatus, ['2', '3', '4'])) {
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
        return (float) \str_replace(',', '.', \str_replace('.', '', $amount));
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
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
        $result['order_id']         = $rawPaymentResponseData['OrderId'];
        $result['transaction_id']   = $rawPaymentResponseData['TransId'];
        $result['auth_code']        = $rawPaymentResponseData['AuthCode'];
        $result['error_code']       = $rawPaymentResponseData['ErrorCode'];
        $result['error_message']    = $rawPaymentResponseData['ErrorMessage'];
        $result['all']              = $rawPaymentResponseData;

        if (self::TX_APPROVED === $result['status']) {
            $result['transaction_time'] = new \DateTimeImmutable($rawPaymentResponseData['TRXDATE']);
        }

        $this->logger->debug('mapped payment response', $result);

        return $result;
    }


    /**
     * returns mapped data of the common response data among all 3d models.
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param array<string, string> $raw3DAuthResponseData
     * @param array<string, string> $rawPaymentResponseData
     * @param string                $txType
     * @param string                $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3DCommonResponseData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $mdStatus            = $this->extractMdStatus($raw3DAuthResponseData);
        $procReturnCode      = $this->getProcReturnCode($raw3DAuthResponseData);
        $paymentResponseData = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->map3DPaymentResponse($rawPaymentResponseData, $txType, $paymentModel);
        }

        $threeDResponse = [
            'order_id'             => $paymentResponseData['order_id'] ?? $raw3DAuthResponseData['OrderId'],
            'proc_return_code'     => $paymentResponseData['proc_return_code'] ?? $procReturnCode,
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'payment_model'        => $paymentModel,
            'md_status'            => $mdStatus,
            'masked_number'        => $raw3DAuthResponseData['Pan'],
            'month'                => null,
            'year'                 => null,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['PurchAmount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['Currency']),
            'transaction_time'     => !isset($paymentResponseData['transaction_time']) && isset($raw3DAuthResponseData['TRXDATE']) ? new \DateTimeImmutable($raw3DAuthResponseData['TRXDATE']) : null,
            'eci'                  => $raw3DAuthResponseData['Eci'],
             /**
             * TxnStat 3D doğrulama sonucunu belirtir :
             * Y : Başarılı
             * N : Başarısız
             * A : Half Secure)
             * U : Teknik Hata
             * E : Hata
             */
            'tx_status'            => $raw3DAuthResponseData['TxnStat'],
            'cavv'                 => null,
            'md_error_message'     => $raw3DAuthResponseData['ErrorMessage'],
            'error_code'           => $paymentResponseData['error_code'] ?? $raw3DAuthResponseData['ErrorCode'],
            'error_message'        => $paymentResponseData['error_message'] ?? $raw3DAuthResponseData['ErrorMessage'],
            'status_detail'        => $paymentResponseData['status_detail'] ?? $this->getStatusDetail($procReturnCode),
        ];

        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            $threeDResponse['3d_all'] = $raw3DAuthResponseData;
        }

        return $this->mergeArraysPreferNonNullValues($paymentResponseData, $threeDResponse);
    }
}
