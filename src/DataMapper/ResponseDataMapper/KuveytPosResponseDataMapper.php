<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

class KuveytPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        'ApiUserNotDefined'          => 'invalid_transaction',
        'EmptyMDException'           => 'invalid_transaction',
        'HashDataError'              => 'invalid_transaction',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $result                 = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $result;
        }

        $status         = self::TX_DECLINED;
        $procReturnCode = $this->getProcReturnCode($rawPaymentResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result['proc_return_code'] = $procReturnCode;
        $result['status']           = $status;
        $result['status_detail']    = $this->getStatusDetail($procReturnCode);
        $result['all']              = $rawPaymentResponseData;

        if (self::TX_APPROVED !== $status) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawPaymentResponseData['ResponseMessage'];
            $this->logger->debug('mapped payment response', $result);

            return $result;
        }

        /** @var array<string, string> $vPosMessage */
        $vPosMessage = $rawPaymentResponseData['VPosMessage'];

        // ProvisionNumber: Başarılı işlemlerde kart bankasının vermiş olduğu otorizasyon numarasıdır.
        $result['auth_code']       = $rawPaymentResponseData['ProvisionNumber'];
        $result['order_id']        = $rawPaymentResponseData['MerchantOrderId'];
        $result['remote_order_id'] = $rawPaymentResponseData['OrderId'];
        // RRN:  Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['ref_ret_num'] = $rawPaymentResponseData['RRN'];
        $result['batch_num']   = $vPosMessage['BatchID'];
        // Stan: Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['transaction_id']    = $rawPaymentResponseData['Stan'];
        $result['amount']            = $this->valueFormatter->formatAmount($vPosMessage['Amount'], $txType);
        $result['currency']          = $this->valueMapper->mapCurrency($vPosMessage['CurrencyCode'], $txType);
        $result['installment_count'] = $this->valueFormatter->formatInstallment($vPosMessage['InstallmentCount'], $txType);
        $result['masked_number']     = $vPosMessage['CardNumber'];
        $result['transaction_time']  = $this->valueFormatter->formatDateTime('now', $txType);

        $this->logger->debug('mapped payment response', $result);

        return $result;
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
        $threeDResponse = $this->map3DCommonResponseData($raw3DAuthResponseData, $txType);
        /** @var PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType */
        $txType = $threeDResponse['transaction_type'] ?? $txType;
        if (null === $rawPaymentResponseData || [] === $rawPaymentResponseData) {
            /** @var PosInterface::MODEL_3D_* $paymentModel */
            $paymentModel = $threeDResponse['payment_model'];

            return $this->mergeArraysPreferNonNullValues(
                $this->getDefaultPaymentResponse($txType, $paymentModel),
                $threeDResponse
            );
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData, $txType, $order);

        $paymentResponseData['payment_model'] = $threeDResponse['payment_model'];

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
     * @param array $rawResponseData
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    public function mapCancelResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
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
        return self::PROCEDURE_SUCCESS_CODE === $mdStatus;
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $this->getProcReturnCode($raw3DAuthResponseData);
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
        return $response['ResponseCode'] ?? null;
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
        return $this->codes[$procReturnCode] ?? $procReturnCode;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        // todo implement
        return 'MPI fallback';
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     *
     * @param array<string, string>       $raw3DAuthResponseData
     * @param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @return array<string, mixed>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData, string $txType): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        /** @var array<string, string> $vPosMessage */
        $vPosMessage = [];
        if (isset($raw3DAuthResponseData['VPosMessage'])) {
            /** @var array<string, string> $vPosMessage */
            $vPosMessage = $raw3DAuthResponseData['VPosMessage'];
            $orderId     = $vPosMessage['MerchantOrderId'];
        } else {
            $orderId = $raw3DAuthResponseData['MerchantOrderId'] ?? null;
        }

        $default = [
            'order_id'             => $orderId,
            'transaction_security' => $this->mapResponseTransactionSecurity('todo'),
            'transaction_type'     => isset($vPosMessage['TransactionType']) ? $this->valueMapper->mapTxType($vPosMessage['TransactionType']) : null,
            'proc_return_code'     => $procReturnCode,
            'md_status'            => null,
            'payment_model'        => null,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'amount'               => null,
            'currency'             => null,
            'masked_number'        => null,
            'tx_status'            => null,
            'error_code'           => self::TX_APPROVED !== $status ? $procReturnCode : null,
            'md_error_message'     => self::TX_APPROVED !== $status ? $raw3DAuthResponseData['ResponseMessage'] : null,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $default['payment_model'] = $this->valueMapper->mapSecureType($vPosMessage['TransactionSecurity'], $txType);
            $default['amount']        = $this->valueFormatter->formatAmount($vPosMessage['Amount'], $txType);
            $default['currency']      = $this->valueMapper->mapCurrency($vPosMessage['CurrencyCode'], $txType);
            $default['masked_number'] = $vPosMessage['CardNumber'];
            $default['batch_num']     = $vPosMessage['BatchID'] > 0 ? $vPosMessage['BatchID'] : null;
        }

        return $default;
    }
}
