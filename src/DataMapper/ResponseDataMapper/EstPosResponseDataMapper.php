<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

/**
 * @phpstan-type PaymentStatusModel array<string, mixed>
 */
class EstPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,

        '01' => 'bank_call',
        '02' => 'bank_call',
        '05' => 'reject',
        '09' => 'try_again',
        '12' => 'invalid_transaction',
        '28' => 'reject',
        '51' => 'insufficient_balance',
        '54' => 'expired_card',
        '57' => 'does_not_allow_card_holder',
        '62' => 'restricted_card',
        '77' => 'request_rejected',
        '99' => 'general_error',
    ];

    /**
     * @param PaymentStatusModel $rawPaymentResponseData
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        if ([] === $rawPaymentResponseData) {
            return $this->getDefaultPaymentResponse();
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);

        $procReturnCode = $this->getProcReturnCode($rawPaymentResponseData);
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['OrderId'],
            'group_id'         => $rawPaymentResponseData['GroupId'],
            'trans_id'         => $rawPaymentResponseData['TransId'],
            'auth_code'        => $rawPaymentResponseData['AuthCode'],
            'ref_ret_num'      => $rawPaymentResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED === $status ? null : $rawPaymentResponseData['Extra']['ERRORCODE'],
            'error_message'    => self::TX_APPROVED === $status ? null : $rawPaymentResponseData['ErrMsg'],
            'recurring_id'     => $rawPaymentResponseData['Extra']['RECURRINGID'] ?? null, // set when recurring payment is made
            'extra'            => $rawPaymentResponseData['Extra'],
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * @param PaymentStatusModel|null $rawPaymentResponseData
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $paymentResponseData   = $this->getDefaultPaymentResponse();
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['mdStatus']),
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'order_id'             => $raw3DAuthResponseData['oid'],
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $this->formatAmount($raw3DAuthResponseData['amount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['currency']),
            'eci'                  => null,
            'tx_status'            => null,
            'cavv'                 => null,
            'md_error_message'     => '1' !== $raw3DAuthResponseData['mdStatus'] ? $raw3DAuthResponseData['mdErrorMsg'] : null,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        if ('1' === $raw3DAuthResponseData['mdStatus']) {
            $threeDResponse['eci']  = $raw3DAuthResponseData['eci'];
            $threeDResponse['cavv'] = $raw3DAuthResponseData['cavv'];
        }

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData($raw3DAuthResponseData): array
    {
        $status = self::TX_DECLINED;

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && in_array($raw3DAuthResponseData['mdStatus'], ['1', '2', '3', '4'])) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse();

        $response = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['mdStatus']),
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'status'               => $status,
            'proc_return_code'     => $procReturnCode,
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $this->formatAmount($raw3DAuthResponseData['amount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['currency']),
            'tx_status'            => null,
            'eci'                  => null,
            'cavv'                 => null,
            'md_error_message'     => $raw3DAuthResponseData['mdErrorMsg'],
            'all'                  => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $response['auth_code']     = $raw3DAuthResponseData['AuthCode'];
            $response['eci']           = $raw3DAuthResponseData['eci'];
            $response['cavv']          = $raw3DAuthResponseData['cavv'];
            $response['trans_id']      = $raw3DAuthResponseData['TransId'];
            $response['ref_ret_num']   = $raw3DAuthResponseData['HostRefNum'];
            $response['status_detail'] = $this->getStatusDetail($procReturnCode);
            $response['error_message'] = $raw3DAuthResponseData['ErrMsg'];
            $response['error_code']    = isset($raw3DAuthResponseData['ErrMsg']) ? $procReturnCode : null;
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;

        if (in_array($raw3DAuthResponseData['mdStatus'], [1, 2, 3, 4])) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse();

        $response = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['mdStatus']),
            'md_status'            => $raw3DAuthResponseData['mdStatus'],
            'status'               => $status,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['amount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['currency']),
            'tx_status'            => null,
            'masked_number'        => null,
            'month'                => null,
            'year'                 => null,
            'eci'                  => null,
            'cavv'                 => null,
            'md_error_message'     => self::TX_APPROVED !== $status ? $raw3DAuthResponseData['mdErrorMsg'] : null,
            'all'                  => $raw3DAuthResponseData,
        ];

        if (isset($raw3DAuthResponseData['maskedCreditCard'])) {
            $response['masked_number'] = $raw3DAuthResponseData['maskedCreditCard'];
            $response['month']         = $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'];
            $response['year']          = $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'];
            if (self::TX_APPROVED === $status) {
                $response['eci']  = $raw3DAuthResponseData['eci'];
                $response['cavv'] = $raw3DAuthResponseData['cavv'];
            }
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $response);
    }

    /**
     * @param PaymentStatusModel $rawResponseData
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        /** @var PaymentStatusModel $rawResponseData */
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => $rawResponseData['GroupId'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'error_code'       => $rawResponseData['Extra']['ERRORCODE'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param PaymentStatusModel $rawResponseData
     *
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        /** @var PaymentStatusModel $rawResponseData */
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        if (isset($rawResponseData['RECURRINGOPERATION'])) {
            if ('Successfull' === $rawResponseData['RESULT']) {
                $status = self::TX_APPROVED;
            }

            return [
                'order_id' => $rawResponseData['RECORDID'],
                'status'   => $status,
                'all'      => $rawResponseData,
            ];
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => $rawResponseData['GroupId'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_code'       => $rawResponseData['Extra']['ERRORCODE'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param PaymentStatusModel $rawResponseData
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

        $extra = $rawResponseData['Extra'];

        if (isset($extra['RECURRINGID'])) {
            return $this->mapRecurringStatusResponse($rawResponseData);
        }

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'auth_code'        => null,
            'proc_return_code' => $procReturnCode,
            'trans_id'         => $rawResponseData['TransId'],
            'error_message'    => $rawResponseData['ErrMsg'],
            'ref_ret_num'      => null,
            'order_status'     => $extra['ORDERSTATUS'],
            'transaction_type' => null,
            'masked_number'    => null,
            'num_code'         => null,
            'first_amount'     => null,
            'capture_amount'   => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'capture'          => false,
            'all'              => $rawResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $result['auth_code']      = $extra['AUTH_CODE'];
            $result['ref_ret_num']    = $extra['HOST_REF_NUM'];
            $result['first_amount']   = $this->formatAmount($extra['ORIG_TRANS_AMT']);
            $result['capture_amount'] = null !== $extra['CAPTURE_AMT'] ? $this->formatAmount($extra['CAPTURE_AMT']) : null;
            $result['masked_number']  = $extra['PAN'];
            $result['num_code']       = $extra['NUMCODE'];
            $result['capture']        = $result['first_amount'] === $result['capture_amount'];
        }

        return $result;
    }


    /**
     * @param array<string, string|int> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapRecurringStatusResponse(array $rawResponseData): array
    {
        $status = self::TX_DECLINED;
        /** @var array<string, string> $extra */
        $extra = $rawResponseData['Extra'];
        if (isset($extra['RECURRINGCOUNT']) && $extra['RECURRINGCOUNT'] > 0) {
            // when order not found for the given recurring order id then RECURRINGCOUNT = 0
            $status = self::TX_APPROVED;
        }

        $recurringOrderResponse = [
            'recurringId'               => $extra['RECURRINGID'],
            'recurringInstallmentCount' => $extra['RECURRINGCOUNT'],
            'status'                    => $status,
            'num_code'                  => $extra['NUMCODE'],
            'error_message'             => $status !== self::TX_APPROVED ? $rawResponseData['ErrMsg'] : null,
            'all'                       => $rawResponseData,
        ];

        for ($i = 1; isset($extra[sprintf('ORD_ID_%d', $i)]); ++$i) {
            $recurringOrder = [
                'order_id'         => $extra[sprintf('ORD_ID_%d', $i)],
                'order_status'     => $extra[sprintf('ORDERSTATUS_%d', $i)],
                'masked_number'    => $extra[sprintf('PAN_%d', $i)],
                'status'           => $extra[sprintf('TRANS_STAT_%d', $i)], //C => Completed, PN => Pending, CNCL => Canceled

                // following fields are null until transaction is done for respective installment:
                'auth_code'        => $extra[sprintf('AUTH_CODE_%d', $i)] ?? null,
                'auth_time'        => $extra[sprintf('AUTH_DTTM_%d', $i)] ?? null,
                'proc_return_code' => $extra[sprintf('PROC_RET_CD_%d', $i)] ?? null,
                'trans_id'         => $extra[sprintf('TRANS_ID_%d', $i)] ?? null,
                'ref_ret_num'      => $extra[sprintf('HOST_REF_NUM_%d', $i)] ?? null,
                'first_amount'     => $extra[sprintf('ORIG_TRANS_AMT_%d', $i)],
                'capture_amount'   => $extra[sprintf('CAPTURE_AMT_%d', $i)] ?? null,
                'capture_time'     => $extra[sprintf('CAPTURE_DTTM_%d', $i)] ?? null,
            ];

            $recurringOrder['capture'] = $recurringOrder['first_amount'] === $recurringOrder['capture_amount'];

            $recurringOrderResponse['recurringOrders'][] = $recurringOrder;
        }

        return $recurringOrderResponse;
    }

    /**
     * @param PaymentStatusModel $rawResponseData
     *
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {

        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        return [
            /** @var PaymentStatusModel $rawResponseData */
            'order_id'         => $rawResponseData['OrderId'],
            'proc_return_code' => $procReturnCode,
            'error_message'    => $rawResponseData['ErrMsg'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'trans_count'      => $rawResponseData['Extra']['TRXCOUNT'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
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
     * "100001" => 1000.01 odeme durum sorgulandiginda gelen amount format
     * "1000.01" => 1000.01 odeme yapildiginda gelen amount format
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((float) \str_replace('.', '', $amount)) / 100;
    }
}
