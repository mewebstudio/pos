<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;

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
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return EstV3Pos::class === $gatewayClass || EstPos::class === $gatewayClass;
    }

    /**
     * @param PaymentStatusModel $rawPaymentResponseData
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);

        $procReturnCode = $this->getProcReturnCode($rawPaymentResponseData);
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $extra = $rawPaymentResponseData['Extra'];

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['OrderId'],
            'currency'         => $order['currency'],
            'amount'           => $order['amount'],
            'group_id'         => $rawPaymentResponseData['GroupId'],
            'transaction_id'   => $rawPaymentResponseData['TransId'],
            'transaction_time' => self::TX_APPROVED === $status ? $this->valueFormatter->formatDateTime($extra['TRXDATE'], $txType) : null,
            'auth_code'        => $rawPaymentResponseData['AuthCode'] ?? null,
            'ref_ret_num'      => $rawPaymentResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED === $status ? null : $extra['ERRORCODE'],
            'error_message'    => self::TX_APPROVED === $status ? null : $rawPaymentResponseData['ErrMsg'],
            'recurring_id'     => $extra['RECURRINGID'] ?? null, // set when recurring payment is made
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * @param PaymentStatusModel|null $rawPaymentResponseData
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $paymentModel          = $this->valueMapper->mapSecureType($raw3DAuthResponseData['storetype'], $txType);
        $paymentResponseData   = $this->getDefaultPaymentResponse($txType, $paymentModel);
        $mdStatus              = $this->extractMdStatus($raw3DAuthResponseData);
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData, $txType, $order);
        }

        $threeDResponse = [
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'md_status'            => $mdStatus,
            'order_id'             => $raw3DAuthResponseData['oid'],
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => null !== $raw3DAuthResponseData['amount'] ? $this->valueFormatter->formatAmount($raw3DAuthResponseData['amount'], $txType) : null,
            'currency'             => $this->valueMapper->mapCurrency($raw3DAuthResponseData['currency'], $txType),
            'installment_count'    => $this->valueFormatter->formatInstallment($raw3DAuthResponseData['taksit'], $txType),
            'eci'                  => null,
            'tx_status'            => null,
            'cavv'                 => null,
            'md_error_message'     => null,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        if (null !== $mdStatus) {
            if (!$this->is3dAuthSuccess($mdStatus)) {
                $threeDResponse['md_error_message'] = $raw3DAuthResponseData['mdErrorMsg'];
            }
        } else {
            $threeDResponse['error_code'] = $raw3DAuthResponseData['ErrorCode'];
            $threeDResponse['error_message'] = $raw3DAuthResponseData['ErrMsg'];
        }

        if ($this->is3dAuthSuccess($mdStatus)) {
            $threeDResponse['eci']  = $raw3DAuthResponseData['eci'];
            $threeDResponse['cavv'] = $raw3DAuthResponseData['cavv'];
        }

        $result                  = $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
        $result['payment_model'] = $paymentModel;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $status = self::TX_DECLINED;

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        $mdStatus              = $this->extractMdStatus($raw3DAuthResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $this->is3dAuthSuccess($mdStatus)) {
            $status = self::TX_APPROVED;
        }

        $paymentModel    = $this->valueMapper->mapSecureType($raw3DAuthResponseData['storetype'], $txType);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $response = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'md_status'            => $mdStatus,
            'status'               => $status,
            'proc_return_code'     => $procReturnCode,
            'masked_number'        => $raw3DAuthResponseData['maskedCreditCard'],
            'month'                => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Month'],
            'year'                 => $raw3DAuthResponseData['Ecom_Payment_Card_ExpDate_Year'],
            'amount'               => $this->valueFormatter->formatAmount($raw3DAuthResponseData['amount'], $txType),
            'currency'             => $this->valueMapper->mapCurrency($raw3DAuthResponseData['currency'], $txType),
            'installment_count'    => $this->valueFormatter->formatInstallment($raw3DAuthResponseData['taksit'], $txType),
            'tx_status'            => null,
            'eci'                  => null,
            'cavv'                 => null,
            'md_error_message'     => $raw3DAuthResponseData['mdErrorMsg'],
            'all'                  => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $response['auth_code']        = $raw3DAuthResponseData['AuthCode'];
            $response['eci']              = $raw3DAuthResponseData['eci'];
            $response['cavv']             = $raw3DAuthResponseData['cavv'];
            $response['transaction_id']   = $raw3DAuthResponseData['TransId'];
            $response['transaction_time'] = $this->valueFormatter->formatDateTime($raw3DAuthResponseData['EXTRA_TRXDATE'], $txType);
            $response['ref_ret_num']      = $raw3DAuthResponseData['HostRefNum'];
            $response['status_detail']    = $this->getStatusDetail($procReturnCode);
            $response['error_message']    = $raw3DAuthResponseData['ErrMsg'];
            $response['error_code']       = isset($raw3DAuthResponseData['ErrMsg']) ? $procReturnCode : null;
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;
        $mdStatus              = $this->extractMdStatus($raw3DAuthResponseData);
        if ($this->is3dAuthSuccess($mdStatus)) {
            $status = self::TX_APPROVED;
        }

        $paymentModel    = $this->valueMapper->mapSecureType($raw3DAuthResponseData['storetype'], $txType);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $response = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'md_status'            => $mdStatus,
            'status'               => $status,
            'amount'               => $this->valueFormatter->formatAmount($raw3DAuthResponseData['amount'], $txType),
            'currency'             => $this->valueMapper->mapCurrency($raw3DAuthResponseData['currency'], $txType),
            'installment_count'    => $this->valueFormatter->formatInstallment($raw3DAuthResponseData['taksit'], $txType),
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
                $response['eci']              = $raw3DAuthResponseData['eci'];
                $response['cavv']             = $raw3DAuthResponseData['cavv'];
                $response['transaction_time'] = $this->valueFormatter->formatDateTime('now', $txType);
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

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => $rawResponseData['TransId'],
            'num_code'         => null,
            'error_code'       => null,
            'error_message'    => $rawResponseData['ErrMsg'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $result['group_id']  = $rawResponseData['GroupId'];
            $result['auth_code'] = $rawResponseData['AuthCode'];
            $result['num_code']  = $rawResponseData['Extra']['NUMCODE'];
        } else {
            $result['error_code'] = $rawResponseData['Extra']['ERRORCODE'] ?? $rawResponseData['ERRORCODE'] ?? null;
        }

        return $result;
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

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'group_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => $rawResponseData['HostRefNum'],
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => $rawResponseData['TransId'],
            'error_code'       => null,
            'num_code'         => null,
            'error_message'    => $rawResponseData['ErrMsg'],
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];

        if (self::TX_APPROVED === $status) {
            $result['group_id']  = $rawResponseData['GroupId'];
            $result['auth_code'] = $rawResponseData['AuthCode'];
            $result['num_code']  = $rawResponseData['Extra']['NUMCODE'];
        } else {
            $result['error_code'] = $rawResponseData['Extra']['ERRORCODE'] ?? $rawResponseData['ERRORCODE'] ?? null;
        }

        return $result;
    }

    /**
     * @param PaymentStatusModel $rawResponseData
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_STATUS;
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

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $defaultResponse['order_id']         = $rawResponseData['OrderId'];
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['transaction_id']   = $rawResponseData['TransId'];
        $defaultResponse['error_message']    = self::TX_APPROVED === $status ? null : $rawResponseData['ErrMsg'];
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);

        $result = $defaultResponse;
        if (self::TX_APPROVED === $status) {
            $result['auth_code']        = $extra['AUTH_CODE'];
            $result['ref_ret_num']      = $extra['HOST_REF_NUM'];
            $result['first_amount']     = $this->valueFormatter->formatAmount($extra['ORIG_TRANS_AMT'], $txType);
            $result['capture_amount']   = null !== $extra['CAPTURE_AMT'] ? $this->valueFormatter->formatAmount($extra['CAPTURE_AMT'], $txType) : null;
            $result['masked_number']    = $extra['PAN'];
            $result['num_code']         = $extra['NUMCODE'];
            $result['capture']          = $result['first_amount'] === $result['capture_amount'];
            $result['transaction_type'] = $this->valueMapper->mapTxType($extra['CHARGE_TYPE_CD']);
            $result['order_status']     = $this->valueMapper->mapOrderStatus($extra['TRANS_STAT']);
            $result['transaction_time'] = isset($extra['AUTH_DTTM']) ? $this->valueFormatter->formatDateTime($extra['AUTH_DTTM'], $txType) : null;
            $result['capture_time']     = isset($extra['CAPTURE_DTTM']) ? $this->valueFormatter->formatDateTime($extra['CAPTURE_DTTM'], $txType) : null;
            $result['cancel_time']      = isset($extra['VOID_DTTM']) ? $this->valueFormatter->formatDateTime($extra['VOID_DTTM'], $txType) : null;
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

        for ($i = 1; isset($extra[\sprintf('ORD_ID_%d', $i)]); ++$i) {
            $recurringOrderResponse['recurringOrders'][] = $this->mapSingleRecurringOrderStatus($extra, $i);
        }

        return $recurringOrderResponse;
    }

    /**
     * @param PaymentStatusModel $rawResponseData
     *
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $transactions = [];
        $i            = 1;
        while (isset($rawResponseData['Extra']['TRX'.$i])) {
            $rawTx          = \explode("\t", $rawResponseData['Extra']['TRX'.$i]);
            $transactions[] = $this->mapSingleOrderHistoryTransaction($rawTx);
            ++$i;
        }

        return [
            /** @var PaymentStatusModel $rawResponseData */
            'order_id'         => $rawResponseData['OrderId'],
            'proc_return_code' => $procReturnCode,
            'error_message'    => $rawResponseData['ErrMsg'],
            'num_code'         => $rawResponseData['Extra']['NUMCODE'],
            'trans_count'      => (int) $rawResponseData['Extra']['TRXCOUNT'],
            'transactions'     => \array_reverse($transactions),
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
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        return $mdStatus === '1';
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['mdStatus'] ?? null;
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
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $txType                          = PosInterface::TX_TYPE_ORDER_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['auth_code']        = $rawTx[8];
        $transaction['proc_return_code'] = $rawTx[9];
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']  = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['transaction_id'] = $rawTx[10];

        $transaction['transaction_type'] = $this->valueMapper->mapTxType($rawTx[0]);
        $transaction['order_status']     = $this->valueMapper->mapOrderStatus($rawTx[1]);
        $transaction['transaction_time'] = $this->valueFormatter->formatDateTime($rawTx[4], $txType);
        $transaction['first_amount']     = null === $rawTx[2] ? null : $this->valueFormatter->formatAmount($rawTx[2], PosInterface::TX_TYPE_ORDER_HISTORY);
        $transaction['capture_amount']   = null === $rawTx[3] ? null : $this->valueFormatter->formatAmount($rawTx[3], PosInterface::TX_TYPE_ORDER_HISTORY);
        $transaction['capture']          = self::TX_APPROVED === $transaction['status'] && $transaction['first_amount'] === $transaction['capture_amount'];
        $transaction['ref_ret_num']      = $rawTx[7];

        return $transaction;
    }

    /**
     * @param array<string|int, string|null> $extra
     * @param int<1, max>                    $i
     *
     * @return array<string, string|float|null>
     */
    private function mapSingleRecurringOrderStatus(array $extra, int $i): array
    {
        $txType         = PosInterface::TX_TYPE_STATUS;
        $procReturnCode = $extra[\sprintf('PROC_RET_CD_%d', $i)] ?? null;
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        } elseif (null === $procReturnCode) {
            $status = null;
        }

        $transStat = $extra[\sprintf('TRANS_STAT_%d', $i)];
        $chargeType = $extra[\sprintf('CHARGE_TYPE_CD_%d', $i)];

        $recurringOrder = [
            'order_id'         => $extra[\sprintf('ORD_ID_%d', $i)],
            'masked_number'    => $extra[\sprintf('PAN_%d', $i)],
            'order_status'     => null === $transStat ? null : $this->valueMapper->mapOrderStatus($transStat),

            // following fields are null until transaction is done for respective installment:
            'auth_code'        => $extra[\sprintf('AUTH_CODE_%d', $i)] ?? null,
            'proc_return_code' => $procReturnCode,
            'transaction_type' => null === $chargeType ? null : $this->valueMapper->mapTxType($chargeType),
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'transaction_time' => isset($extra[\sprintf('AUTH_DTTM_%d', $i)]) ? $this->valueFormatter->formatDateTime($extra[\sprintf('AUTH_DTTM_%d', $i)], $txType) : null,
            'capture_time'     => isset($extra[\sprintf('CAPTURE_DTTM_%d', $i)]) ? $this->valueFormatter->formatDateTime($extra[\sprintf('CAPTURE_DTTM_%d', $i)], $txType) : null,
            'transaction_id'   => $extra[\sprintf('TRANS_ID_%d', $i)] ?? null,
            'ref_ret_num'      => $extra[\sprintf('HOST_REF_NUM_%d', $i)] ?? null,
            'first_amount'     => isset($extra[\sprintf('ORIG_TRANS_AMT_%d', $i)]) ? $this->valueFormatter->formatAmount(
                $extra[\sprintf('ORIG_TRANS_AMT_%d', $i)],
                $txType
            ) : null,
            'capture_amount'   => isset($extra[\sprintf('CAPTURE_AMT_%d', $i)]) ? $this->valueFormatter->formatAmount(
                $extra[\sprintf('CAPTURE_AMT_%d', $i)],
                $txType
            ) : null,
        ];


        $recurringOrder['capture'] = $recurringOrder['first_amount'] === $recurringOrder['capture_amount'];

        return $this->mergeArraysPreferNonNullValues($this->getDefaultOrderHistoryTxResponse(), $recurringOrder);
    }
}
