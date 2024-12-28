<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * maps responses of AkbankPos
 */
class AkbankPosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = 'VPS-0000';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
    ];

    /**
     * N: Normal
     * S: Şüpheli
     * V: İptal
     * R: Reversal
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    private array $orderStatusMappings = [
        'N'         => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'S'         => PosInterface::PAYMENT_STATUS_ERROR,
        'V'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'R'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,

        // status that are return on history request
        'Başarılı'  => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'Başarısız' => PosInterface::PAYMENT_STATUS_ERROR,
        'İptal'     => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    private array $recurringOrderStatusMappings = [
        'S' => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'W' => PosInterface::PAYMENT_STATUS_PAYMENT_PENDING,
        // when fulfilled payment is canceled
        'V' => PosInterface::PAYMENT_STATUS_CANCELED,
        // when unfulfilled payment is canceled
        'C' => PosInterface::PAYMENT_STATUS_CANCELED,
    ];

    /**
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

        $mappedResponse = [
            'recurring_id'      => $rawPaymentResponseData['order']['orderTrackId'] ?? null,
            'transaction_type'  => $this->mapTxType($rawPaymentResponseData['txnCode']),
            'currency'          => $order['currency'],
            'amount'            => $order['amount'],
            'installment_count' => isset($rawPaymentResponseData['order']['orderTrackId']) ? null : $order['installment'] ?? null,
            'transaction_id'    => null,
            'transaction_time'  => self::TX_APPROVED === $status ? new \DateTimeImmutable($rawPaymentResponseData['txnDateTime']) : null,
            'proc_return_code'  => $procReturnCode,
            'status'            => $status,
            'status_detail'     => $this->getStatusDetail($procReturnCode),
            'error_code'        => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'     => self::TX_APPROVED === $status ? null : $rawPaymentResponseData['hostMessage'] ?? $rawPaymentResponseData['responseMessage'],
            'all'               => $rawPaymentResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $mappedResponse['order_id']    = $rawPaymentResponseData['order']['orderId'];
            $mappedResponse['batch_num']   = $rawPaymentResponseData['transaction']['batchNumber'];
            $mappedResponse['auth_code']   = $rawPaymentResponseData['transaction']['authCode'];
            $mappedResponse['ref_ret_num'] = $rawPaymentResponseData['transaction']['rrn'];
        } else {
            $mappedResponse['order_id'] = $rawPaymentResponseData['order']['orderId'] ?? null;
            if (isset($rawPaymentResponseData['transaction'])) {
                $mappedResponse['auth_code']   = isset($rawPaymentResponseData['transaction']['authCode'])
                && $rawPaymentResponseData['transaction']['authCode'] > 0
                    ? $rawPaymentResponseData['transaction']['authCode']
                    : null;
                $mappedResponse['batch_num']   = $rawPaymentResponseData['transaction']['batchNumber'] > 0 ? $rawPaymentResponseData['transaction']['batchNumber'] : null;
                $mappedResponse['ref_ret_num'] = $rawPaymentResponseData['transaction']['rrn'] > 0 ? $rawPaymentResponseData['transaction']['rrn'] : null;
            }
        }

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
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
        $paymentModel          = PosInterface::MODEL_3D_SECURE;
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $paymentResponseData   = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData, $txType, $order);
        }

        $threeDResponse           = $this->map3DResponseData($raw3DAuthResponseData, $paymentModel);
        $threeDResponse['3d_all'] = $raw3DAuthResponseData;

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

        $paymentModel    = PosInterface::MODEL_3D_PAY;
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $threeDResponse = $this->map3DResponseData($raw3DAuthResponseData, $paymentModel);

        $defaultResponse['all']               = $raw3DAuthResponseData;
        $defaultResponse['status']            = $status;
        $defaultResponse['amount']            = $order['amount'];
        $defaultResponse['currency']          = $order['currency'];
        $defaultResponse['installment_count'] = $order['installment'];

        if (self::TX_APPROVED === $status) {
            $defaultResponse['transaction_time'] = new \DateTimeImmutable($raw3DAuthResponseData['txnDateTime']);
            $defaultResponse['auth_code']        = $raw3DAuthResponseData['authCode'];
            $defaultResponse['batch_num']        = (int) $raw3DAuthResponseData['batchNumber'];
            $defaultResponse['ref_ret_num']      = $raw3DAuthResponseData['rrn'];
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $threeDResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $result = $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);

        $result['payment_model'] = PosInterface::MODEL_3D_HOST;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'auth_code'        => null,
            'recurring_id'     => $rawResponseData['order']['orderTrackId'] ?? null,
            'ref_ret_num'      => null,
            'transaction_id'   => null,
            'proc_return_code' => $procReturnCode,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $result['order_id']    = $rawResponseData['order']['orderId'];
            $result['auth_code']   = $rawResponseData['transaction']['authCode'];
            $result['ref_ret_num'] = $rawResponseData['transaction']['rrn'];
        } else {
            $result['order_id']      = $rawResponseData['order']['orderId'] ?? null;
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['responseMessage'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $status          = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'recurring_id'     => $rawResponseData['order']['orderTrackId'] ?? null,
            'auth_code'        => null,
            'ref_ret_num'      => null,
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => null,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'all'              => $rawResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            // not yet fulfilled recurring payments does not have orderId
            $result['order_id'] = $rawResponseData['order']['orderId'] ?? null;
        } else {
            $result['order_id']      = $rawResponseData['order']['orderId'] ?? null;
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResponseData['responseMessage'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
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

        $transactions     = [];
        $orderId          = null;
        $isRecurringOrder = false;
        if (self::TX_APPROVED === $status) {
            foreach ($rawResponseData['txnDetailList'] as $rawTx) {
                if (isset($rawTx['requestType']) && 'R' === $rawTx['requestType']) {
                    $isRecurringOrder = true;
                    $transactions[]   = $this->mapSingleRecurringOrderHistoryTransaction($rawTx);
                } else {
                    $transactions[] = $this->mapSingleOrderHistoryTransaction($rawTx);
                }

                $orderId ??= $rawTx['orgOrderId'] ?? $rawTx['orderId'] ?? null;
            }
        }

        if (!$isRecurringOrder) {
            \usort($transactions, static function (array $tx1, array $tx2): int {
                if (null !== $tx1['transaction_time'] && null === $tx2['transaction_time']) {
                    return 1;
                }

                if (null === $tx1['transaction_time'] && null !== $tx2['transaction_time']) {
                    return -1;
                }

                if ($tx1['transaction_time'] == $tx2['transaction_time']) {
                    return 0;
                }

                if ($tx1['transaction_time'] < $tx2['transaction_time']) {
                    return -1;
                }

                return 1;
            });
        }

        return [
            'order_id'         => $orderId,
            'proc_return_code' => $procReturnCode,
            'error_message'    => self::TX_APPROVED === $status ? null : $rawResponseData['responseMessage'],
            'error_code'       => self::TX_APPROVED === $status ? null : $procReturnCode,
            'trans_count'      => \count($transactions),
            'transactions'     => $transactions,
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
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $procReturnCode     = $this->getProcReturnCode($rawResponseData);
        $status             = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
            foreach ($rawResponseData['data']['txnDetailList'] as $rawTx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

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
            $result['error_message'] = $rawResponseData['responseMessage'];
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
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        return '';
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
        return $response['responseCode'] ?? null;
    }

    /**
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return (float) $amount;
    }

    /**
     * @param string|null $installment
     *
     * @return int
     */
    protected function mapInstallment(?string $installment): int
    {
        return $installment > 1 ? (int) $installment : 0;
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
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']     = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['currency']          = $this->mapCurrency($rawTx['currencyCode']);
        $transaction['installment_count'] = $this->mapInstallment($rawTx['installCount']);
        $transaction['transaction_type']  = $this->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);
        $transaction['transaction_time']  = new \DateTimeImmutable($rawTx['txnDateTime']);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['masked_number'] = $rawTx['maskedCardNumber'];
            $transaction['ref_ret_num']   = $rawTx['rrn'];
            // batchNumber is not provided when payment is canceled
            $transaction['batch_num']    = $rawTx['batchNumber'] ?? null;
            $transaction['order_status'] = $this->mapOrderStatus($rawTx['txnStatus'], $rawTx['preAuthStatus'] ?? null);
            $transaction['auth_code']    = $rawTx['authCode'];
            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transaction['order_status']) {
                if (\in_array(
                    $transaction['transaction_type'],
                    [
                            PosInterface::TX_TYPE_PAY_AUTH,
                            PosInterface::TX_TYPE_PAY_POST_AUTH,
                        ],
                    true,
                )
                ) {
                    $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = new \DateTimeImmutable($rawTx['txnDateTime']);
                    }
                } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction['transaction_type']) {
                    $transaction['capture_amount'] = null === $rawTx['preAuthCloseAmount'] ? null : $this->formatAmount($rawTx['preAuthCloseAmount']);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = new \DateTimeImmutable($rawTx['preAuthCloseDate']);
                    }
                }
            }
        } else {
            $transaction['error_code'] = $transaction['proc_return_code'];
        }

        return $transaction;
    }

    /**
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleRecurringOrderHistoryTransaction(array $rawTx): array
    {
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        $transaction['order_status']     = $this->mapRecurringOrderStatus($rawTx['requestStatus']);
        if (null === $transaction['proc_return_code']) {
            // no proc return code since this is the pending payment
            $transaction['status'] = null;
        } elseif (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']     = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['recurring_order']   = $rawTx['recurringOrder'];
        $transaction['masked_number']     = $rawTx['maskedCardNumber'];
        $transaction['currency']          = $this->mapCurrency($rawTx['currencyCode']);
        $transaction['installment_count'] = $this->mapInstallment($rawTx['installCount']);
        $transaction['transaction_type']  = $this->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['auth_code'] = $rawTx['authCode'];
            if (PosInterface::PAYMENT_STATUS_PAYMENT_PENDING !== $transaction['order_status']) {
                $transaction['transaction_time'] = new \DateTimeImmutable($rawTx['txnDateTime']);
            }

            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transaction['order_status']) {
                $transaction['batch_num']      = $rawTx['batchNumber'];
                $transaction['ref_ret_num']    = $rawTx['rrn'];
                $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);
                $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                $transaction['capture_time']   = new \DateTimeImmutable($rawTx['txnDateTime']);
            }
        } else {
            $transaction['error_code'] = $transaction['proc_return_code'];
        }

        return $transaction;
    }

    /**
     * @param array<string, string|null> $rawTx
     *
     * @return array<string, int|string|null|float|bool|\DateTimeImmutable>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['order_id']      = null;
        $transaction['status_detail'] = $this->getStatusDetail($transaction['proc_return_code']);

        $transaction['currency']          = $this->mapCurrency($rawTx['currencyCode']);
        $transaction['installment_count'] = $this->mapInstallment($rawTx['installmentCount']);
        $transaction['transaction_type']  = $this->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);
        $transaction['transaction_time']  = new \DateTimeImmutable($rawTx['txnDateTime']);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['order_id']      = $rawTx['orderId'];
            $transaction['masked_number'] = $rawTx['maskedCardNumber'];
            $transaction['ref_ret_num']   = $rawTx['rrn'];
            // batchNumber is not provided when payment is canceled
            $transaction['batch_num']    = $rawTx['batchNumber'] ?? null;
            $transaction['order_status'] = $this->mapOrderStatus($rawTx['txnStatus'], $rawTx['preAuthStatus'] ?? null);
            $transaction['auth_code']    = $rawTx['authCode'];
            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transaction['order_status']) {
                if (\in_array(
                    $transaction['transaction_type'],
                    [
                        PosInterface::TX_TYPE_PAY_AUTH,
                        PosInterface::TX_TYPE_PAY_POST_AUTH,
                    ],
                    true,
                )) {
                    $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->formatAmount($rawTx['amount']);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = new \DateTimeImmutable($rawTx['txnDateTime']);
                    }
                } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction['transaction_type']) {
                    $transaction['capture_amount'] = null === $rawTx['preAuthCloseAmount'] ? null : $this->formatAmount($rawTx['preAuthCloseAmount']);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = new \DateTimeImmutable($rawTx['preAuthCloseDate']);
                    }
                }
            }
        } else {
            $transaction['error_code'] = $transaction['proc_return_code'];
        }

        return $transaction;
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_* $paymentModel
     *
     * @param array<string, mixed> $raw3DAuthResponseData
     * @param string               $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3DResponseData(array $raw3DAuthResponseData, string $paymentModel): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        $is3DAuthSuccess       = $this->is3dAuthSuccess($procReturnCode);

        $threeDResponse = [
            'proc_return_code'     => $procReturnCode,
            'payment_model'        => $paymentModel,
            'transaction_security' => null,
            'md_status'            => null,
            'order_id'             => $raw3DAuthResponseData['orderId'],
            'masked_number'        => null,
            'eci'                  => null,
            'md_error_message'     => $is3DAuthSuccess ? null : $raw3DAuthResponseData['responseMessage'],
        ];

        if ($is3DAuthSuccess && PosInterface::MODEL_3D_SECURE === $paymentModel) {
            $threeDResponse['eci'] = $raw3DAuthResponseData['secureEcomInd'];
        }

        return $threeDResponse;
    }

    /**
     * @param string      $txStatus
     * @param string|null $preAuthStatus
     *
     * @return string
     */
    private function mapOrderStatus(string $txStatus, ?string $preAuthStatus): string
    {
        $orderStatus = $this->orderStatusMappings[$txStatus];
        /**
         * preAuthStatus
         * "O": Açık
         * "C": Kapalı
         */
        if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $orderStatus && 'O' === $preAuthStatus) {
            return PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
        }

        return $orderStatus;
    }

    /**
     * @param string $requestStatus
     *
     * @return string
     */
    private function mapRecurringOrderStatus(string $requestStatus): string
    {
        return $this->recurringOrderStatusMappings[$requestStatus] ?? $requestStatus;
    }
}
