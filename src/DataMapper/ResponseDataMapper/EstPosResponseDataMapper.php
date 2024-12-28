<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
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
     * D : Başarısız işlem
     * A : Otorizasyon, gün sonu kapanmadan
     * C : Ön otorizasyon kapama, gün sonu kapanmadan
     * PN : Bekleyen İşlem
     * CNCL : İptal Edilmiş İşlem
     * ERR : Hata Almış İşlem
     * S : Satış
     * R : Teknik İptal gerekiyor
     * V : İptal
     * @var array<string, string>
     */
    protected array $orderStatusMappings = [
        'D'    => PosInterface::PAYMENT_STATUS_ERROR,
        'ERR'  => PosInterface::PAYMENT_STATUS_ERROR,
        'A'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'C'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'    => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'PN'   => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        'CNCL' => PosInterface::PAYMENT_STATUS_CANCELED,
        'V'    => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

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
            'transaction_time' => self::TX_APPROVED === $status ? new \DateTimeImmutable($extra['TRXDATE']) : null,
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
        $paymentModel          = $this->mapSecurityType($raw3DAuthResponseData['storetype']);
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
            'amount'               => null !== $raw3DAuthResponseData['amount'] ? $this->formatAmount($raw3DAuthResponseData['amount']) : null,
            'currency'             => '*' === $raw3DAuthResponseData['currency'] ? null : $this->mapCurrency($raw3DAuthResponseData['currency']),
            'installment_count'    => $this->mapInstallment($raw3DAuthResponseData['taksit']),
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

        $paymentModel    = $this->mapSecurityType($raw3DAuthResponseData['storetype']);
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
            'amount'               => $this->formatAmount($raw3DAuthResponseData['amount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['currency']),
            'installment_count'    => $this->mapInstallment($raw3DAuthResponseData['taksit']),
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
            $response['transaction_time'] = new \DateTimeImmutable($raw3DAuthResponseData['EXTRA_TRXDATE']);
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

        $paymentModel    = $this->mapSecurityType($raw3DAuthResponseData['storetype']);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $response = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'md_status'            => $mdStatus,
            'status'               => $status,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['amount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['currency']),
            'installment_count'    => $this->mapInstallment($raw3DAuthResponseData['taksit']),
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
                $response['transaction_time'] = new \DateTimeImmutable();
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
            $result['first_amount']     = $this->formatAmount($extra['ORIG_TRANS_AMT']);
            $result['capture_amount']   = null !== $extra['CAPTURE_AMT'] ? $this->formatAmount($extra['CAPTURE_AMT']) : null;
            $result['masked_number']    = $extra['PAN'];
            $result['num_code']         = $extra['NUMCODE'];
            $result['capture']          = $result['first_amount'] === $result['capture_amount'];
            $txType                     = 'S' === $extra['CHARGE_TYPE_CD'] ? PosInterface::TX_TYPE_PAY_AUTH : PosInterface::TX_TYPE_REFUND;
            $result['transaction_type'] = $txType;
            $result['order_status']     = $this->orderStatusMappings[$extra['TRANS_STAT']] ?? null;
            $result['transaction_time'] = isset($extra['AUTH_DTTM']) ? new \DateTimeImmutable($extra['AUTH_DTTM']) : null;
            $result['capture_time']     = isset($extra['CAPTURE_DTTM']) ? new \DateTimeImmutable($extra['CAPTURE_DTTM']) : null;
            $result['cancel_time']      = isset($extra['VOID_DTTM']) ? new \DateTimeImmutable($extra['VOID_DTTM']) : null;
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
     *
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((float) \str_replace('.', '', $amount)) / 100;
    }

    /**
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['auth_code']        = $rawTx[8];
        $transaction['proc_return_code'] = $rawTx[9];
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']  = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['transaction_id'] = $rawTx[10];
        /**
         * S: Auth/PreAuth/PostAuth
         * C: Refund
         */
        $transaction['transaction_type'] = 'S' === $rawTx[0] ? PosInterface::TX_TYPE_PAY_AUTH : PosInterface::TX_TYPE_REFUND;
        $transaction['order_status']     = $this->orderStatusMappings[$rawTx[1]] ?? null;
        $transaction['transaction_time'] = new \DateTimeImmutable($rawTx[4]);
        $transaction['first_amount']     = null === $rawTx[2] ? null : $this->formatAmount($rawTx[2]);
        $transaction['capture_amount']   = null === $rawTx[3] ? null : $this->formatAmount($rawTx[3]);
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
        $procReturnCode = $extra[\sprintf('PROC_RET_CD_%d', $i)] ?? null;
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        } elseif (null === $procReturnCode) {
            $status = null;
        }

        $recurringOrder = [
            'order_id'         => $extra[\sprintf('ORD_ID_%d', $i)],
            'masked_number'    => $extra[\sprintf('PAN_%d', $i)],
            'order_status'     => $this->orderStatusMappings[$extra[\sprintf('TRANS_STAT_%d', $i)]] ?? null,

            // following fields are null until transaction is done for respective installment:
            'auth_code'        => $extra[\sprintf('AUTH_CODE_%d', $i)] ?? null,
            'proc_return_code' => $procReturnCode,
            'transaction_type' => 'S' === $extra[\sprintf('CHARGE_TYPE_CD_%d', $i)] ? PosInterface::TX_TYPE_PAY_AUTH : PosInterface::TX_TYPE_REFUND,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'transaction_time' => isset($extra[\sprintf('AUTH_DTTM_%d', $i)]) ? new \DateTimeImmutable($extra[\sprintf('AUTH_DTTM_%d', $i)]) : null,
            'capture_time'     => isset($extra[\sprintf('CAPTURE_DTTM_%d', $i)]) ? new \DateTimeImmutable($extra[\sprintf('CAPTURE_DTTM_%d', $i)]) : null,
            'transaction_id'   => $extra[\sprintf('TRANS_ID_%d', $i)] ?? null,
            'ref_ret_num'      => $extra[\sprintf('HOST_REF_NUM_%d', $i)] ?? null,
            'first_amount'     => isset($extra[\sprintf('ORIG_TRANS_AMT_%d', $i)]) ? $this->formatAmount($extra[\sprintf('ORIG_TRANS_AMT_%d', $i)]) : null,
            'capture_amount'   => isset($extra[\sprintf('CAPTURE_AMT_%d', $i)]) ? $this->formatAmount($extra[\sprintf('CAPTURE_AMT_%d', $i)]) : null,
        ];


        $recurringOrder['capture'] = $recurringOrder['first_amount'] === $recurringOrder['capture_amount'];

        return $this->mergeArraysPreferNonNullValues($this->getDefaultOrderHistoryTxResponse(), $recurringOrder);
    }
}
