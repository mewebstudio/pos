<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Psr\Log\LogLevel;

class KuveytPosResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        'ApiUserNotDefined' => 'invalid_transaction',
        'EmptyMDException'  => 'invalid_transaction',
        'HashDataError'     => 'invalid_transaction',
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

        $result['auth_code']     = $rawPaymentResponseData['ProvisionNumber'];
        $result['order_id']      = $rawPaymentResponseData['MerchantOrderId'];
        $result['ref_ret_num']  = $rawPaymentResponseData['RRN'];
        $result['amount']        = $vPosMessage['Amount'];
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
            '3d_auth_response' => $raw3DAuthResponseData,
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
        $procReturnCode = $this->getProcReturnCode($raw3DAuthResponseData);
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        /** @var array<string, string> $vPosMessage */
        $vPosMessage = [];
        if (isset($raw3DAuthResponseData['VPosMessage'])) {
            /** @var array<string, string> $vPosMessage */
            $vPosMessage = $raw3DAuthResponseData['VPosMessage'];
            $orderId = $vPosMessage['MerchantOrderId'];
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
            $default['amount'] = $vPosMessage['Amount'];
            $default['currency'] = $this->mapCurrency($vPosMessage['CurrencyCode']);
            $default['masked_number'] = $vPosMessage['CardNumber'];
        }

        return $default;
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
