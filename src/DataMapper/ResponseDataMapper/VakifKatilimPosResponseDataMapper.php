<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

class VakifKatilimPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        'CardNotEnrolled'            => 'reject',
        '51'                         => 'reject',
    ];

    /**
     * Order Status Codes
     *
     * @var array<int, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        4 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5 => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        6 => PosInterface::PAYMENT_STATUS_CANCELED,
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
        $result['order_id']         = $rawPaymentResponseData['MerchantOrderId'];
        $result['remote_order_id']  = $rawPaymentResponseData['OrderId'];
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
        // RRN:  Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['ref_ret_num'] = $rawPaymentResponseData['RRN'];
        $result['batch_num']   = $vPosMessage['BatchID'];
        // Stan: Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['transaction_id']    = $rawPaymentResponseData['Stan'];
        $result['masked_number']     = $vPosMessage['CardNumber'];
        $result['amount']            = $this->formatAmount($vPosMessage['Amount']);
        $result['currency']          = $this->mapCurrency($vPosMessage['CurrencyCode']);
        $result['installment_count'] = $this->mapInstallment($vPosMessage['InstallmentCount']);
        if ('0001-01-01T00:00:00' !== $rawPaymentResponseData['TransactionTime'] && '00010101T00:00:00' !== $rawPaymentResponseData['TransactionTime']) {
            $result['transaction_time'] = new \DateTimeImmutable($rawPaymentResponseData['TransactionTime']);
        } else {
            $result['transaction_time'] = new \DateTimeImmutable();
        }


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
        $threeDResponse = $this->map3DCommonResponseData($raw3DAuthResponseData);
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

        $paymentResponseData = $this->map3DPaymentPaymentResponse($rawPaymentResponseData, $txType, $order);

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
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
        ]);

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_HOST);

        $status         = self::TX_DECLINED;
        $procReturnCode = $this->getProcReturnCode($raw3DAuthResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['md_status']            = null;
        $defaultResponse['md_error_message']     = null;
        $defaultResponse['transaction_security'] = null;
        $defaultResponse['proc_return_code']     = $procReturnCode;
        $defaultResponse['status']               = $status;
        $defaultResponse['status_detail']        = $this->getStatusDetail($procReturnCode);
        $defaultResponse['all']                  = $raw3DAuthResponseData;

        if (self::TX_APPROVED !== $status) {
            $defaultResponse['error_code']    = $procReturnCode;
            $defaultResponse['error_message'] = $raw3DAuthResponseData['ResponseMessage'];
            $this->logger->debug('mapped payment response', $defaultResponse);

            return $defaultResponse;
        }

        // ProvisionNumber: Başarılı işlemlerde kart bankasının vermiş olduğu otorizasyon numarasıdır.
        $defaultResponse['order_id']        = $raw3DAuthResponseData['MerchantOrderId'];
        $defaultResponse['remote_order_id'] = $raw3DAuthResponseData['OrderId'];
        // RRN:  Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $defaultResponse['ref_ret_num'] = $raw3DAuthResponseData['RRN'];
        // Stan: Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $defaultResponse['transaction_id']   = $raw3DAuthResponseData['Stan'];
        $defaultResponse['auth_code']        = $raw3DAuthResponseData['ProvisionNumber'] ?? null;
        $defaultResponse['transaction_time'] = new \DateTimeImmutable();

        return $defaultResponse;
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $mappedTransactions = $this->mapSingleHistoryTransaction($rawResponseData['VPosOrderData']['OrderContract']);

            return \array_merge($defaultResponse, $mappedTransactions);
        }

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['error_code']       = $procReturnCode;
        $defaultResponse['error_message']    = $rawResponseData['ResponseMessage'];
        $defaultResponse['order_id']         = $rawResponseData['MerchantOrderId'];

        return $defaultResponse;
    }

    /**
     * @inheritDoc
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;

        $result = [
            'order_id'         => null,
            'auth_code'        => null,
            'proc_return_code' => null,
            'transaction_id'   => null,
            'currency'         => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => null,
            'all'              => $rawResponseData,
        ];

        $vposMessage    = $rawResponseData['VPosMessage'];
        $procReturnCode = $this->getProcReturnCode($rawResponseData);

        if (null === $procReturnCode) {
            return $result;
        }

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result['proc_return_code'] = $procReturnCode;
        $result['order_id']         = $vposMessage['MerchantOrderId'];
        $result['remote_order_id']  = (string) $rawResponseData['OrderId'];
        $result['status']           = $status;

        if (self::TX_APPROVED !== $status) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['ResponseMessage'];
        } else {
            $result['transaction_id'] = $rawResponseData['Stan'];
            $result['ref_ret_num']    = $rawResponseData['RRN'];
        }

        return $result;
    }


    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $procReturnCode     = $this->getProcReturnCode($rawResponseData);
        $status             = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        if (isset($rawResponseData['VPosOrderData']['OrderContract'])) {
            foreach ($rawResponseData['VPosOrderData']['OrderContract'] as $tx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($tx);
            }
        }

        $mappedTransactions = \array_reverse($mappedTransactions);

        $result = [
            'proc_return_code' => $procReturnCode,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'status_detail'    => null !== $procReturnCode ? $this->getStatusDetail($procReturnCode) : null,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];

        if (null !== $procReturnCode && self::PROCEDURE_SUCCESS_CODE !== $procReturnCode) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['ResponseMessage'];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $procReturnCode     = $this->getProcReturnCode($rawResponseData);
        $status             = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $orderId       = null;
        $remoteOrderId = null;
        if (isset($rawResponseData['VPosOrderData']['OrderContract'])) {
            foreach ($rawResponseData['VPosOrderData']['OrderContract'] as $tx) {
                $mappedTransactions[] = $this->mapSingleOrderHistoryTransaction($tx);
                $orderId              = $tx['MerchantOrderId'];
                $remoteOrderId        = $tx['OrderId'];
            }
        }

        $result = [
            'proc_return_code' => $procReturnCode,
            'order_id'         => $orderId,
            'remote_order_id'  => $remoteOrderId,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'status_detail'    => null !== $procReturnCode ? $this->getStatusDetail($procReturnCode) : null,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];

        if (null !== $procReturnCode && self::PROCEDURE_SUCCESS_CODE !== $procReturnCode) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['ResponseMessage'];
        }

        return $result;
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
     * "101" => 1.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return (float) $amount / 100;
    }

    /**
     * Get ProcReturnCode
     *
     * @param array<string, string|null> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ResponseCode'] ?? null;
    }

    /**
     * @param string $currency currency code that is accepted by bank
     *
     * @return PosInterface::CURRENCY_*|string
     */
    protected function mapCurrency(string $currency): string
    {
        // 949 => 0949; for the request gateway wants 0949 code, but in response they send 949 code.
        $currencyNormalized = \str_pad($currency, 4, '0', STR_PAD_LEFT);

        return parent::mapCurrency($currencyNormalized);
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
        return 'MPI fallback';
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     *
     * @param array<string, string> $raw3DAuthResponseData
     *
     * @return array<string, mixed>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $orderId = $raw3DAuthResponseData['MerchantOrderId'] ?? null;

        return [
            'order_id'             => $orderId,
            'transaction_security' => null,
            'transaction_type'     => null,
            'proc_return_code'     => $procReturnCode,
            'md_status'            => null,
            'payment_model'        => PosInterface::MODEL_3D_SECURE,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'amount'               => null,
            'currency'             => null,
            'tx_status'            => null,
            'error_code'           => self::TX_APPROVED !== $status ? $procReturnCode : null,
            'md_error_message'     => self::TX_APPROVED !== $status ? $raw3DAuthResponseData['ResponseMessage'] : null,
            '3d_all'               => $raw3DAuthResponseData,
        ];
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $response                    = $this->mapSingleOrderHistoryTransaction($rawTx);
        $response['order_id']        = $rawTx['MerchantOrderId'];
        $response['remote_order_id'] = $rawTx['OrderId'];

        return $response;
    }


    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $procReturnCode = $this->getProcReturnCode($rawTx);
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultOrderHistoryTxResponse();

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);
        $defaultResponse['error_code']       = self::TX_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['error_message']    = self::TX_APPROVED === $status ? null : $rawTx['ResponseExplain'];
        $defaultResponse['currency']         = null !== $rawTx['FEC'] ? $this->mapCurrency($rawTx['FEC']) : null;
        $defaultResponse['payment_model']    = null !== $rawTx['TransactionSecurity'] ? $this->mapSecurityType($rawTx['TransactionSecurity']) : null;
        $defaultResponse['ref_ret_num']      = $rawTx['RRN'];
        $defaultResponse['transaction_id']   = $rawTx['Stan'];
        $defaultResponse['transaction_time'] = null !== $rawTx['OrderDate'] ? new \DateTimeImmutable($rawTx['OrderDate']) : null;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['auth_code']         = $rawTx['ProvNumber'] ?? null;
            $defaultResponse['installment_count'] = $this->mapInstallment($rawTx['InstallmentCount']);
            $defaultResponse['masked_number']     = $rawTx['CardNumber'];
            $defaultResponse['first_amount']      = null === $rawTx['FirstAmount'] ? null : (float) $rawTx['FirstAmount'];
            $defaultResponse['order_status']      = $this->orderStatusMappings[$rawTx['LastOrderStatus']] ?? $rawTx['LastOrderStatusDescription'];
            $initialOrderStatus                   = $this->orderStatusMappings[$rawTx['OrderStatus']] ?? null;

            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $initialOrderStatus) {
                $defaultResponse['capture_amount'] = isset($rawTx['TranAmount']) ? (float) $rawTx['TranAmount'] : 0;
                $defaultResponse['capture']        = $defaultResponse['first_amount'] === $defaultResponse['capture_amount'];
                if ($defaultResponse['capture']) {
                    $defaultResponse['capture_time'] = $defaultResponse['transaction_time'];
                }
            } elseif (PosInterface::PAYMENT_STATUS_CANCELED === $initialOrderStatus) {
                $defaultResponse['cancel_time'] = $defaultResponse['transaction_time'];
            }
        }

        return $defaultResponse;
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    private function map3DPaymentPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
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
        $result['order_id']        = $rawPaymentResponseData['MerchantOrderId'];
        $result['remote_order_id'] = $rawPaymentResponseData['OrderId'];
        // RRN:  Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['ref_ret_num'] = $rawPaymentResponseData['RRN'];
        // Stan: Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['transaction_id']    = $rawPaymentResponseData['Stan'];
        $result['batch_num']         = $vPosMessage['BatchId'];
        $result['auth_code']         = $rawPaymentResponseData['ProvisionNumber'] ?? null;
        $result['masked_number']     = $vPosMessage['CardNumber'] ?? null;
        $result['currency']          = isset($vPosMessage['CurrencyCode']) ? $this->mapCurrency($vPosMessage['CurrencyCode']) : $order['currency'];
        $result['amount']            = $this->formatAmount($vPosMessage['Amount']);
        $result['installment_count'] = $this->mapInstallment($vPosMessage['InstallmentCount']);
        if ('0001-01-01T00:00:00' !== $rawPaymentResponseData['TransactionTime'] && '00010101T00:00:00' !== $rawPaymentResponseData['TransactionTime']) {
            $result['transaction_time'] = new \DateTimeImmutable($rawPaymentResponseData['TransactionTime']);
        } else {
            $result['transaction_time'] = new \DateTimeImmutable();
        }

        $this->logger->debug('mapped payment response', $result);

        return $result;
    }
}
