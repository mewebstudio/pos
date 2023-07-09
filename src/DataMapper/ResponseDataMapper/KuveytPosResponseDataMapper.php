<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Psr\Log\LogLevel;

class KuveytPosResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        'ApiUserNotDefined'          => 'invalid_transaction',
        'EmptyMDException'           => 'invalid_transaction',
        'HashDataError'              => 'invalid_transaction',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$rawPaymentResponseData]);

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $result                 = $this->getDefaultPaymentResponse();
        if (empty($rawPaymentResponseData)) {
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
            $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $result);

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
        $result['trans_id']      = $rawPaymentResponseData['Stan'];
        $result['amount']        = self::amountFormat($vPosMessage['Amount']);
        $result['currency']      = $this->mapCurrency($vPosMessage['CurrencyCode']);
        $result['masked_number'] = $vPosMessage['CardNumber'];

        $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $threeDResponse = $this->map3DCommonResponseData($raw3DAuthResponseData);

        if (empty($rawPaymentResponseData)) {
            return array_merge($this->getDefaultPaymentResponse(), $threeDResponse);
        }

        $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);

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
     * Get Status Detail Text
     *
     * @param string|null $procReturnCode
     *
     * @return string|null
     */
    protected function getStatusDetail(?string $procReturnCode): ?string
    {
        return $procReturnCode ? ($this->codes[$procReturnCode] ?? $procReturnCode) : null;
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
            'proc_return_code'     => $procReturnCode,
            'md_status'            => null,
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
            $default['amount']        = $vPosMessage['Amount'];
            $default['currency']      = $this->mapCurrency($vPosMessage['CurrencyCode']);
            $default['masked_number'] = $vPosMessage['CardNumber'];
        }

        return $default;
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

        $result = [
            'order_id'         => null,
            'auth_code'        => null,
            'proc_return_code' => null,
            'trans_id'         => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'order_status'     => null,
            'transaction_type' => null,
            'masked_number'    => null,
            'first_amount'     => null,
            'capture_amount'   => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => null,
            'capture'          => false,
            'all'              => $rawResponseData,
        ];

        if (!isset($data['OrderContract'])) {
            return $result;
        }
        $orderContract  = $rawResponseData['GetMerchantOrderDetailResult']['Value']['OrderContract'];
        $procReturnCode = $this->getProcReturnCode($orderContract);


        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        if (self::TX_APPROVED === $status) {
            $result['proc_return_code'] = $procReturnCode;
            /**
             * ordeme yapildiginda OrderStatus === LastOrderStatus === 1 oluyor
             * LastOrderStatus = 5 => odeme iade edildi
             * LastOrderStatus = 6 => odeme iptal edild
             */
            $result['order_status']     = $orderContract['LastOrderStatus'];
            $result['order_id']         = $orderContract['MerchantOrderId'];
            $result['remote_order_id']  = (string) $orderContract['OrderId'];
            $result['status']           = $status;

            $result['auth_code']      = $orderContract['ProvNumber'];
            $result['ref_ret_num']    = $orderContract['RRN'];
            $result['trans_id']       = $orderContract['Stan'];
            $result['currency']       = $this->mapCurrency($orderContract['FEC']);
            $result['first_amount']   = (float) $orderContract['FirstAmount'];
            $result['capture_amount'] = null !== $orderContract['FirstAmount'] ? (float) $orderContract['FirstAmount'] : null;
            $result['masked_number']  = $orderContract['CardNumber'];
            $result['date']           = $orderContract['OrderDate'];
        }

        return $result;
    }

    public function mapRefundResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;

        $result = [
            'order_id'         => null,
            'auth_code'        => null,
            'proc_return_code' => null,
            'trans_id'         => null,
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
        if ($status !== self::TX_APPROVED && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult = $responseResults['Result'][0];
            $result['error_code'] = $responseResult['ErrorCode'];
            $result['error_message'] = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['trans_id']         = $value['Stan'];
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
            'trans_id'         => null,
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
        if ($status !== self::TX_APPROVED && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult = $responseResults['Result'][0];
            $result['error_code'] = $responseResult['ErrorCode'];
            $result['error_message'] = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['trans_id']         = $value['Stan'];
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

    public function mapHistoryResponse(array $rawResponseData): array
    {
        return $this->emptyStringsToNull($rawResponseData);
    }

    /**
     * "101" => 1.01
     * @param string $amount
     *
     * @return float
     */
    public static function amountFormat(string $amount): float
    {
        return (float) $amount / 100;
    }


    /**
     * @param string $currency TRY, USD
     *
     * @return string currency code that is accepted by bank
     */
    protected function mapCurrency(string $currency): string
    {
        // 949 => 0949; for the request gateway wants 0949 code, but in response they send 949 code.
        $currencyNormalized = str_pad($currency, 4, '0', STR_PAD_LEFT);

        return parent::mapCurrency($currencyNormalized);
    }
}
