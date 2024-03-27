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
     * Order Status Codes
     *
     * @var array<int, string>
     */
    protected array $orderStatusMappings = [
        1 => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        5 => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
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
        // Stan: Pos bankası tarafında verilen referans işlem referans numarasıdır.
        $result['transaction_id']    = $rawPaymentResponseData['Stan'];
        $result['amount']            = $this->formatAmount($vPosMessage['Amount']);
        $result['currency']          = $this->mapCurrency($vPosMessage['CurrencyCode']);
        $result['installment_count'] = $this->mapInstallment($vPosMessage['InstallmentCount']);
        $result['masked_number']     = $vPosMessage['CardNumber'];
        $result['transaction_time']  = new \DateTimeImmutable();

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

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData, $txType, $order);

        $paymentResponseData['payment_model'] = $threeDResponse['payment_model'];

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->map3DPaymentData($raw3DAuthResponseData, $raw3DAuthResponseData, $txType, $order);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        return $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);
    }

    /**
     * @param array $rawResponseData
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $data            = $rawResponseData['GetMerchantOrderDetailResult']['Value'];

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        if (!isset($data['OrderContract'])) {
            return $defaultResponse;
        }

        $orderContract  = $rawResponseData['GetMerchantOrderDetailResult']['Value']['OrderContract'];
        $procReturnCode = $this->getProcReturnCode($orderContract);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['status']           = $status;
        $defaultResponse['proc_return_code'] = $procReturnCode;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['order_status']    = $this->orderStatusMappings[$orderContract['LastOrderStatus']] ?? null;
            $defaultResponse['order_id']        = $orderContract['MerchantOrderId'];
            $defaultResponse['remote_order_id'] = (string) $orderContract['OrderId'];

            $defaultResponse['auth_code']         = $orderContract['ProvNumber'];
            $defaultResponse['ref_ret_num']       = $orderContract['RRN'];
            $defaultResponse['transaction_id']    = $orderContract['Stan'];
            $defaultResponse['currency']          = $this->mapCurrency($orderContract['FEC']);
            $defaultResponse['first_amount']      = (float) $orderContract['FirstAmount'];
            $defaultResponse['capture_amount']    = null !== $orderContract['FirstAmount'] ? (float) $orderContract['FirstAmount'] : null;
            $defaultResponse['capture']           = $defaultResponse['first_amount'] > 0 && $defaultResponse['first_amount'] === $defaultResponse['capture_amount'];
            $defaultResponse['masked_number']     = $orderContract['CardNumber'];
            $defaultResponse['transaction_time']  = new \DateTimeImmutable($orderContract['OrderDate']);
            $defaultResponse['installment_count'] = $this->mapInstallment($orderContract['InstallmentCount']);
        }

        return $defaultResponse;
    }

    public function mapRefundResponse(array $rawResponseData): array
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


        $value          = $rawResponseData['PartialDrawbackResult']['Value'];
        $procReturnCode = $this->getProcReturnCode($value);

        if (null === $procReturnCode) {
            return $result;
        }

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $responseResults = $rawResponseData['PartialDrawbackResult']['Results'];
        if (self::TX_APPROVED !== $status && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult          = $responseResults['Result'][0];
            $result['error_code']    = $responseResult['ErrorCode'];
            $result['error_message'] = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['transaction_id']   = $value['Stan'];
        $result['proc_return_code'] = $procReturnCode;
        $result['order_id']         = $value['MerchantOrderId'];
        $result['remote_order_id']  = (string) $value['OrderId'];
        $result['status']           = $status;
        $result['currency']         = $this->mapCurrency($value['CurrencyCode']);

        if (self::TX_APPROVED === $status) {
            $result['auth_code'] = $value['ProvisionNumber'];
        } else {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $value['ResponseMessage'];
        }

        return $result;
    }

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

        $value          = $rawResponseData['SaleReversalResult']['Value'];
        $procReturnCode = $this->getProcReturnCode($value);

        if (null === $procReturnCode) {
            return $result;
        }

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $responseResults = $rawResponseData['SaleReversalResult']['Results'];
        if (self::TX_APPROVED !== $status && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult          = $responseResults['Result'][0];
            $result['error_code']    = $responseResult['ErrorCode'];
            $result['error_message'] = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['transaction_id']   = $value['Stan'];
        $result['proc_return_code'] = $procReturnCode;
        $result['order_id']         = $value['MerchantOrderId'];
        $result['remote_order_id']  = (string) $value['OrderId'];
        $result['status']           = $status;
        $result['currency']         = $this->mapCurrency($value['CurrencyCode']);

        if (self::TX_APPROVED === $status) {
            $result['auth_code'] = $value['ProvisionNumber'];
        } else {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $value['ResponseMessage'];
        }

        return $result;
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
     * @param array<string, string> $response
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
        $currencyNormalized = str_pad($currency, 4, '0', STR_PAD_LEFT);

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
        // todo implement
        return 'MPI fallback';
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     *
     * @param array<string, string> $raw3DAuthResponseData
     *
     * @return array<string, string>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData): array
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
            $orderId = $raw3DAuthResponseData['MerchantOrderId'];
        }

        $default = [
            'order_id'             => $orderId,
            'transaction_security' => $this->mapResponseTransactionSecurity('todo'),
            'transaction_type'     => isset($vPosMessage['TransactionType']) ? $this->mapTxType($vPosMessage['TransactionType']) : null,
            'proc_return_code'     => $procReturnCode,
            'md_status'            => null,
            'payment_model'        => null,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'amount'               => null,
            'currency'             => null,
            'tx_status'            => null,
            'error_code'           => self::TX_APPROVED !== $status ? $procReturnCode : null,
            'md_error_message'     => self::TX_APPROVED !== $status ? $raw3DAuthResponseData['ResponseMessage'] : null,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $default['payment_model'] = $this->mapSecurityType($vPosMessage['TransactionSecurity']);
            $default['amount']        = $this->formatAmount($vPosMessage['Amount']);
            $default['currency']      = $this->mapCurrency($vPosMessage['CurrencyCode']);
            $default['masked_number'] = $vPosMessage['CardNumber'];
        }

        return $default;
    }
}
