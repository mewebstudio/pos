<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * @phpstan-type PaymentStatusModel array{Order: array<string, string|array<string, string|null>>, Response: array<string, string>, Transaction: array<string, string>|array{Response: array<string, string>}}
 */
class GarantiPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,

        '96' => 'general_error',
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
        '92' => 'invalid_transaction',
        '99' => 'general_error',
    ];

    /**
     * @param PaymentStatusModel $rawPaymentResponseData
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        /** @var PaymentStatusModel $rawPaymentResponseData */
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $status = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        $transaction     = $rawPaymentResponseData['Transaction'];

        /** @var string $provDate */
        $provDate = $transaction['ProvDate'] ?? 'now';

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['Order']['OrderID'],
            'group_id'         => $rawPaymentResponseData['Order']['GroupID'],
            'auth_code'        => self::TX_APPROVED === $status ? $transaction['AuthCode'] : null,
            'ref_ret_num'      => self::TX_APPROVED === $status ? $transaction['RetrefNum'] : null,
            'batch_num'        => self::TX_APPROVED === $status ? $transaction['BatchNum'] : null,
            'transaction_time' => self::TX_APPROVED === $status ? new \DateTimeImmutable($provDate) : null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'currency'         => $order['currency'],
            'amount'           => $order['amount'],
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => self::TX_APPROVED !== $status ? $transaction['Response']['ReasonCode'] : null,
            'error_message'    => self::TX_APPROVED !== $status ? $transaction['Response']['ErrorMsg'] : null,
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
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        /** @var PaymentStatusModel|null $rawPaymentResponseData */
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $this->logger->debug('mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);

        $commonResult = $this->map3DCommonResponseData($raw3DAuthResponseData, PosInterface::MODEL_3D_SECURE);
        $mdStatus     = $this->extractMdStatus($raw3DAuthResponseData);
        // todo refactor
        if ($this->is3dAuthSuccess($mdStatus)) {
            //these data only available on success
            $commonResult['auth_code']      = $raw3DAuthResponseData['authcode'];
            $commonResult['transaction_id'] = $raw3DAuthResponseData['transid'];
            $commonResult['ref_ret_num']    = $raw3DAuthResponseData['hostrefnum'];
            $commonResult['masked_number']  = $raw3DAuthResponseData['MaskedPan'];
            $commonResult['tx_status']      = $raw3DAuthResponseData['txnstatus'];
            $commonResult['eci']            = $raw3DAuthResponseData['eci'];
            $commonResult['cavv']           = $raw3DAuthResponseData['cavv'];
        }

        $paymentStatus          = self::TX_DECLINED;
        $paymentModel           = $this->mapSecurityType($raw3DAuthResponseData['secure3dsecuritylevel']);
        $defaultPaymentResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        $mappedPaymentResponse  = [];
        if (self::TX_APPROVED === $commonResult['status'] && null !== $rawPaymentResponseData) {
            $transaction    = $rawPaymentResponseData['Transaction'];
            $procReturnCode = $this->getProcReturnCode($rawPaymentResponseData);
            if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
                $paymentStatus = self::TX_APPROVED;
            }

            /** @var string $provDate */
            $provDate = $transaction['ProvDate'] ?? 'now';

            $mappedPaymentResponse = [
                'group_id'         => $transaction['SequenceNum'] ?? null,
                'auth_code'        => $transaction['AuthCode'] ?? null,
                'ref_ret_num'      => $transaction['RetrefNum'] ?? null,
                'batch_num'        => $transaction['BatchNum'] ?? null,
                'transaction_time' => self::TX_APPROVED === $paymentStatus ? new \DateTimeImmutable($provDate) : null,
                'error_code'       => self::TX_APPROVED === $paymentStatus ? null : $transaction['Response']['ReasonCode'],
                'error_message'    => self::TX_APPROVED === $paymentStatus ? null : $transaction['Response']['ErrorMsg'],
                'all'              => $rawPaymentResponseData,
                'proc_return_code' => $procReturnCode,
                'status'           => $paymentStatus,
                'status_detail'    => $this->getStatusDetail($procReturnCode),
            ];

            $mappedPaymentResponse = $this->mergeArraysPreferNonNullValues($defaultPaymentResponse, $mappedPaymentResponse);
        }

        if ([] === $mappedPaymentResponse) {
            return $this->mergeArraysPreferNonNullValues($defaultPaymentResponse, $commonResult);
        }

        return $this->mergeArraysPreferNonNullValues($commonResult, $mappedPaymentResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $threeDAuthResult = $this->map3DCommonResponseData($raw3DAuthResponseData, PosInterface::MODEL_3D_PAY);
        $threeDAuthStatus = $threeDAuthResult['status'];
        $paymentStatus    = self::TX_DECLINED;
        $procReturnCode   = $raw3DAuthResponseData['procreturncode'];
        if (self::TX_APPROVED === $threeDAuthStatus && self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $paymentStatus = self::TX_APPROVED;
        }

        $paymentModel = $this->mapSecurityType($raw3DAuthResponseData['secure3dsecuritylevel']);
        /** @var PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType */
        $txType                           = $this->mapTxType($raw3DAuthResponseData['txntype']) ?? $txType;
        $defaultPaymentResponse           = $this->getDefaultPaymentResponse(
            $txType,
            $paymentModel
        );
        $defaultPaymentResponse['status'] = $paymentStatus;

        if (self::TX_APPROVED === $threeDAuthStatus) {
            $threeDAuthResult['auth_code']      = $raw3DAuthResponseData['authcode'];
            $threeDAuthResult['transaction_id'] = $raw3DAuthResponseData['transid'];
            $threeDAuthResult['ref_ret_num']    = $raw3DAuthResponseData['hostrefnum'];
            $threeDAuthResult['masked_number']  = $raw3DAuthResponseData['MaskedPan'];
            $threeDAuthResult['tx_status']      = $raw3DAuthResponseData['txnstatus'];
            $threeDAuthResult['eci']            = $raw3DAuthResponseData['eci'];
            $threeDAuthResult['cavv']           = $raw3DAuthResponseData['cavv'];
        }

        if (self::TX_APPROVED !== $paymentStatus) {
            $defaultPaymentResponse['error_message'] = $raw3DAuthResponseData['errmsg'];
            $defaultPaymentResponse['error_code']    = $procReturnCode;
        } else {
            $defaultPaymentResponse['transaction_time'] = new \DateTimeImmutable();
        }

        return $this->mergeArraysPreferNonNullValues($threeDAuthResult, $defaultPaymentResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * @param PaymentStatusModel|array<string, string> $rawResponseData
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

        $transaction = $rawResponseData['Transaction'];


        return [
            'order_id'         => $rawResponseData['Order']['OrderID'] ?? null,
            'group_id'         => $rawResponseData['Order']['GroupID'] ?? null,
            'transaction_id'   => null,
            'auth_code'        => $transaction['AuthCode'] ?? null,
            'ref_ret_num'      => $transaction['RetrefNum'] ?? null,
            'proc_return_code' => $procReturnCode,
            'error_code'       => $transaction['Response']['Code'] ?? null,
            'error_message'    => $transaction['Response']['ErrorMsg'] ?? null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param PaymentStatusModel|array<string, string> $rawResponseData
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        /** @var PaymentStatusModel $rawResponseData */
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        /** @var array{Response: array<string, string|null>} $transaction */
        $transaction     = $rawResponseData['Transaction'];
        /** @var array<string, string|null> $orderInqResult */
        $orderInqResult  = $rawResponseData['Order']['OrderInqResult'];
        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $orderStatus = $orderInqResult['Status'];
        if ('WAITINGPOSTAUTH' === $orderInqResult['Status']) {
            $orderStatus = PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
        }

        $result = [
            'order_id'          => $rawResponseData['Order']['OrderID'] ?? null,
            'auth_code'         => $orderInqResult['AuthCode'] ?? null,
            'ref_ret_num'       => $orderInqResult['RetrefNum'] ?? null,
            'installment_count' => $this->mapInstallment($orderInqResult['InstallmentCnt']),
            'proc_return_code'  => $procReturnCode,
            'order_status'      => $orderStatus,
            'status'            => $status,
            'status_detail'     => $this->getStatusDetail($procReturnCode),
            'error_code'        => self::TX_APPROVED === $status ? null : $transaction['Response']['Code'],
            'error_message'     => self::TX_APPROVED === $status ? null : $transaction['Response']['ErrorMsg'],
        ];
        if (self::TX_APPROVED === $status) {
            $transTime                  = $orderInqResult['ProvDate'] ?? $orderInqResult['PreAuthDate'];
            $result['transaction_time'] = $transTime === null ? null : new \DateTimeImmutable($transTime);
            $result['capture_time']     = null !== $orderInqResult['AuthDate'] ? new \DateTimeImmutable($orderInqResult['AuthDate']) : null;
            $result['masked_number']    = $orderInqResult['CardNumberMasked'];
            $amount                     = $orderInqResult['AuthAmount'];
            $result['capture_amount']   = null !== $amount ? $this->formatAmount($amount) : null;
            $firstAmount                = $amount > 0 ? $amount : $orderInqResult['PreAuthAmount'];
            $result['first_amount']     = null !== $firstAmount ? $this->formatAmount($firstAmount) : null;
            $result['capture']          = $result['first_amount'] > 0 ? $result['capture_amount'] === $result['first_amount'] : null;
        }

        return \array_merge($defaultResponse, $result);
    }

    /**
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

        $mappedTransactions = [];
        if (self::TX_APPROVED === $status) {
            $rawTransactions = $rawResponseData['Order']['OrderHistInqResult']['OrderTxnList']['OrderTxn'];
            if (\count($rawTransactions) !== \count($rawTransactions, COUNT_RECURSIVE)) {
                foreach ($rawTransactions as $transaction) {
                    $mappedTransaction    = $this->mapSingleOrderHistoryTransaction($transaction);
                    $mappedTransactions[] = $mappedTransaction;
                }
            } else {
                $mappedTransactions[] = $this->mapSingleOrderHistoryTransaction($rawTransactions);
            }
        }

        return [
            'order_id'         => $rawResponseData['Order']['OrderID'],
            'proc_return_code' => $procReturnCode,
            'error_code'       => self::TX_DECLINED === $status ? $procReturnCode : null,
            'error_message'    => self::TX_DECLINED === $status ? $rawResponseData['Transaction']['Response']['ErrorMsg'] : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
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
        return \in_array($mdStatus, ['1', '2', '3', '4'], true);
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['mdstatus'] ?? null;
    }

    /**
     * 100001 => 1000.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((float) $amount) / 100;
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     * @phpstan-param PosInterface::MODEL_3D_* $paymentModel
     *
     * @param array<string, string> $raw3DAuthResponseData
     * @param string                $paymentModel
     *
     * @return array<string, mixed>
     */
    protected function map3DCommonResponseData(array $raw3DAuthResponseData, string $paymentModel): array
    {
        $procReturnCode = $raw3DAuthResponseData['procreturncode'];
        $mdStatus       = $this->extractMdStatus($raw3DAuthResponseData);

        $status = self::TX_DECLINED;

        if ($this->is3dAuthSuccess($mdStatus) && 'Error' !== $raw3DAuthResponseData['response']) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'order_id'             => $raw3DAuthResponseData['oid'],
            'transaction_id'       => null,
            'auth_code'            => null,
            'ref_ret_num'          => null,
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'transaction_type'     => $this->mapTxType($raw3DAuthResponseData['txntype']),
            'proc_return_code'     => $procReturnCode,
            'md_status'            => $mdStatus,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'masked_number'        => null,
            'amount'               => $this->formatAmount($raw3DAuthResponseData['txnamount']),
            'currency'             => $this->mapCurrency($raw3DAuthResponseData['txncurrencycode']),
            'installment_count'    => $this->mapInstallment($raw3DAuthResponseData['txninstallmentcount']),
            'tx_status'            => null,
            'eci'                  => null,
            'cavv'                 => null,
            'error_code'           => 'Error' === $raw3DAuthResponseData['response'] ? $procReturnCode : null,
            'error_message'        => self::TX_APPROVED === $status ? null : $raw3DAuthResponseData['errmsg'],
            'md_error_message'     => self::TX_APPROVED === $status ? null : $raw3DAuthResponseData['mderrormessage'],
        ];

        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            $result['3d_all'] = $raw3DAuthResponseData;
        }

        return $result;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        if (!$this->is3dAuthSuccess($mdStatus)) {
            return 'MPI fallback';
        }

        if ('1' === $mdStatus) {
            return 'Full 3D Secure';
        }

        // ['2', '3', '4']
        return 'Half 3D Secure';
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
     * @phpstan-param PaymentStatusModel                                    $response
     *
     * @param array{Transaction: array{Response: array{Code: string|null}}} $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['Transaction']['Response']['Code'] ?? null;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTime>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $procReturnCode = $rawTx['Status'];
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse                     = $this->getDefaultOrderHistoryTxResponse();
        $defaultResponse['auth_code']        = $rawTx['AuthCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawTx['RetrefNum'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);
        $defaultResponse['error_code']       = self::TX_APPROVED === $status ? null : $procReturnCode;
        $defaultResponse['transaction_type'] = $rawTx['Type'] === null ? null : $this->mapTxType($rawTx['Type']);

        if (self::TX_APPROVED === $status) {
            $transTime                           = $rawTx['ProvDate'] ?? $rawTx['PreAuthDate'] ?? $rawTx['AuthDate'];
            $defaultResponse['transaction_time'] = new \DateTimeImmutable($transTime.'T000000');
            $defaultResponse['capture_time']     = null !== $rawTx['AuthDate'] ? new \DateTimeImmutable($rawTx['AuthDate'].'T000000') : null;
            $amount                              = $rawTx['AuthAmount'];
            $defaultResponse['capture_amount']   = null !== $amount ? $this->formatAmount($amount) : null;
            $firstAmount                         = $amount > 0 ? $amount : $rawTx['PreAuthAmount'];
            $defaultResponse['first_amount']     = null !== $firstAmount ? $this->formatAmount($firstAmount) : null;
            $defaultResponse['capture']          = $defaultResponse['first_amount'] > 0 ? $defaultResponse['capture_amount'] === $defaultResponse['first_amount'] : null;
            $defaultResponse['currency']         = '0' !== $rawTx['CurrencyCode'] && null !== $rawTx['CurrencyCode'] ? $this->mapCurrency($rawTx['CurrencyCode']) : null;
        }

        return $defaultResponse;
    }
}
