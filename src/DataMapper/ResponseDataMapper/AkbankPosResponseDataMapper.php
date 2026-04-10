<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\DataMapper\ResponseValueMapper\AkbankPosResponseValueMapper;
use Mews\Pos\DataMapper\ResponseValueMapper\ResponseValueMapperInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AkbankPos;
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
     * @var AkbankPosResponseValueMapper
     */
    protected ResponseValueMapperInterface $valueMapper;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

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
            'transaction_type'  => $this->valueMapper->mapTxType($rawPaymentResponseData['txnCode']),
            'currency'          => $order['currency'],
            'amount'            => $order['amount'],
            'installment_count' => isset($rawPaymentResponseData['order']['orderTrackId']) ? null : $order['installment'] ?? null,
            'transaction_id'    => null,
            'transaction_time'  => self::TX_APPROVED === $status ? $this->valueFormatter->formatDateTime($rawPaymentResponseData['txnDateTime'], $txType) : null,
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
            $defaultResponse['transaction_time'] = $this->valueFormatter->formatDateTime($raw3DAuthResponseData['txnDateTime'], $txType);
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
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleOrderHistoryTransaction(array $rawTx): array
    {
        $txType                          = PosInterface::TX_TYPE_ORDER_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']     = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['currency']          = $this->valueMapper->mapCurrency($rawTx['currencyCode'], $txType);
        $transaction['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['installCount'], $txType);
        $transaction['transaction_type']  = $this->valueMapper->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
        $transaction['transaction_time']  = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['masked_number'] = $rawTx['maskedCardNumber'];
            $transaction['ref_ret_num']   = $rawTx['rrn'];
            // batchNumber is not provided when payment is canceled
            $transaction['batch_num']    = $rawTx['batchNumber'] ?? null;
            $transaction['order_status'] = $this->valueMapper->mapOrderStatus($rawTx['txnStatus'], $rawTx['preAuthStatus'] ?? null);
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
                    $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);
                    }
                } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction['transaction_type']) {
                    $transaction['capture_amount'] = null === $rawTx['preAuthCloseAmount'] ? null : $this->valueFormatter->formatAmount($rawTx['preAuthCloseAmount'], $txType);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['preAuthCloseDate'], $txType);
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
        $txType                          = PosInterface::TX_TYPE_ORDER_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        $transaction['order_status']     = $this->valueMapper->mapOrderStatus($rawTx['requestStatus'], null, true);
        if (null === $transaction['proc_return_code']) {
            // no proc return code since this is the pending payment
            $transaction['status'] = null;
        } elseif (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['status_detail']     = $this->getStatusDetail($transaction['proc_return_code']);
        $transaction['recurring_order']   = $rawTx['recurringOrder'];
        $transaction['masked_number']     = $rawTx['maskedCardNumber'];
        $transaction['currency']          = $this->valueMapper->mapCurrency($rawTx['currencyCode'], $txType);
        $transaction['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['installCount'], $txType);
        $transaction['transaction_type']  = $this->valueMapper->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['auth_code'] = $rawTx['authCode'];
            if (PosInterface::PAYMENT_STATUS_PAYMENT_PENDING !== $transaction['order_status']) {
                $transaction['transaction_time'] = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);
            }

            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transaction['order_status']) {
                $transaction['batch_num']      = $rawTx['batchNumber'];
                $transaction['ref_ret_num']    = $rawTx['rrn'];
                $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
                $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                $transaction['capture_time']   = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);
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
        $txType                          = PosInterface::TX_TYPE_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $transaction['proc_return_code'] = $this->getProcReturnCode($rawTx);
        if (self::PROCEDURE_SUCCESS_CODE === $transaction['proc_return_code']) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['order_id']      = null;
        $transaction['status_detail'] = $this->getStatusDetail($transaction['proc_return_code']);

        $transaction['currency']          = $this->valueMapper->mapCurrency($rawTx['currencyCode'], $txType);
        $transaction['installment_count'] = $this->valueFormatter->formatInstallment($rawTx['installmentCount'], $txType);
        $transaction['transaction_type']  = $this->valueMapper->mapTxType($rawTx['txnCode']);
        $transaction['first_amount']      = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], $txType);
        $transaction['transaction_time']  = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);

        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['order_id']      = $rawTx['orderId'];
            $transaction['masked_number'] = $rawTx['maskedCardNumber'];
            $transaction['ref_ret_num']   = $rawTx['rrn'];
            // batchNumber is not provided when payment is canceled
            $transaction['batch_num']    = $rawTx['batchNumber'] ?? null;
            $transaction['order_status'] = $this->valueMapper->mapOrderStatus($rawTx['txnStatus'], $rawTx['preAuthStatus'] ?? null);
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
                    $transaction['capture_amount'] = null === $rawTx['amount'] ? null : $this->valueFormatter->formatAmount($rawTx['amount'], PosInterface::TX_TYPE_HISTORY);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['txnDateTime'], $txType);
                    }
                } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $transaction['transaction_type']) {
                    $transaction['capture_amount'] = null === $rawTx['preAuthCloseAmount'] ? null : $this->valueFormatter->formatAmount($rawTx['preAuthCloseAmount'], PosInterface::TX_TYPE_HISTORY);
                    $transaction['capture']        = $transaction['first_amount'] === $transaction['capture_amount'];
                    if ($transaction['capture']) {
                        $transaction['capture_time'] = $this->valueFormatter->formatDateTime($rawTx['preAuthCloseDate'], $txType);
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
}
