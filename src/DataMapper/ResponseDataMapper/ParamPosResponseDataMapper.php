<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;

class ParamPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);

        $currency = $order['currency'] ?? PosInterface::CURRENCY_TRY;
        if (PosInterface::TX_TYPE_PAY_PRE_AUTH === $txType) {
            $payResult = $rawPaymentResponseData['TP_Islem_Odeme_OnProv_WMDResponse']['TP_Islem_Odeme_OnProv_WMDResult'];
        } elseif (PosInterface::TX_TYPE_PAY_POST_AUTH === $txType) {
            $payResult = $rawPaymentResponseData['TP_Islem_Odeme_OnProv_KapaResponse']['TP_Islem_Odeme_OnProv_KapaResult'];
        } elseif (PosInterface::CURRENCY_TRY !== $currency) {
            $payResult = $rawPaymentResponseData['TP_Islem_Odeme_WDResponse']['TP_Islem_Odeme_WDResult'];
        } else {
            $payResult = $rawPaymentResponseData['TP_WMD_UCDResponse']['TP_WMD_UCDResult'];
        }

        $procReturnCode = $this->getProcReturnCode($payResult);
        $status         = self::TX_DECLINED;
        if ($procReturnCode > 0 && PosInterface::TX_TYPE_PAY_POST_AUTH === $txType) {
            $status = self::TX_APPROVED;
        } elseif ($procReturnCode > 0 && $payResult['Islem_ID'] > 0) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'error_code'       => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'    => self::TX_APPROVED === $status ? null : ($payResult['Sonuc_Str'] ?? $payResult['Bank_HostMsg']),
            'all'              => $rawPaymentResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            if (PosInterface::CURRENCY_TRY === $currency) {
                if (PosInterface::TX_TYPE_PAY_PRE_AUTH !== $txType) {
                    $mappedResponse['ref_ret_num'] = $payResult['Bank_HostRefNum'];
                }

                $mappedResponse['order_id']       = $payResult['Siparis_ID'];
                $mappedResponse['transaction_id'] = $payResult['Bank_Trans_ID'];
                $mappedResponse['auth_code']      = $payResult['Bank_AuthCode'];
            } else {
                $mappedResponse['order_id'] = $order['id'];
            }

            $mappedResponse['amount']           = $order['amount'];
            $mappedResponse['currency']         = $currency;
            $mappedResponse['transaction_time'] = $this->valueFormatter->formatDateTime('now', $txType);
        }

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * @param array<string, mixed>        $rawPaymentResponseData
     * @param PosInterface::TX_TYPE_PAY_* $txType
     * @param array<string, mixed>        $order
     *
     * @return array<string, mixed>
     */
    private function map3DPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);

        $payResult = $rawPaymentResponseData['TP_WMD_PayResponse']['TP_WMD_PayResult'];

        $procReturnCode = $this->getProcReturnCode($payResult);
        $status         = self::TX_DECLINED;
        if ($procReturnCode > 0 && $payResult['Dekont_ID'] > 0) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'error_code'       => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'    => self::TX_APPROVED === $status ? null : ($payResult['Sonuc_Ack'] ?? $payResult['Bank_HostMsg']),
            'all'              => $rawPaymentResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $mappedResponse['order_id']         = $payResult['Siparis_ID'];
            $mappedResponse['transaction_id']   = $payResult['Bank_Trans_ID'];
            $mappedResponse['auth_code']        = $payResult['Bank_AuthCode'];
            $mappedResponse['ref_ret_num']      = $payResult['Bank_HostRefNum'];
            $mappedResponse['currency']         = $order['currency'];
            $mappedResponse['transaction_time'] = $this->valueFormatter->formatDateTime('now', $txType);
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
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $paymentModel          = PosInterface::MODEL_3D_SECURE;
        $paymentResponseData   = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if (null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->map3DPaymentResponse($rawPaymentResponseData, $txType, $order);
        }

        $mdStatus = $this->extractMdStatus($raw3DAuthResponseData);

        $threeDResponse = [
            'transaction_security' => null,
            'md_status'            => $mdStatus,
            'order_id'             => $raw3DAuthResponseData['orderId'],
            'amount'               => null !== $raw3DAuthResponseData['transactionAmount']
                ? $this->valueFormatter->formatAmount($raw3DAuthResponseData['transactionAmount'], $txType) : null,
            'currency'             => $order['currency'],
            'installment_count'    => null,
            'md_error_message'     => null,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        $result                  = $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
        $result['payment_model'] = $paymentModel;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$raw3DAuthResponseData]);

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_PAY);

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $procReturnCode = $this->getProcReturnCode($raw3DAuthResponseData);
        $status         = self::TX_DECLINED;
        if ($procReturnCode > 0 && $raw3DAuthResponseData['TURKPOS_RETVAL_Dekont_ID'] > 0) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'md_status'            => null,
            'md_error_message'     => null,
            'transaction_security' => null,
            'proc_return_code'     => $procReturnCode,
            'status'               => $status,
            'error_code'           => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'        => self::TX_APPROVED === $status ? null : $raw3DAuthResponseData['TURKPOS_RETVAL_Sonuc_Str'],
            'all'                  => $raw3DAuthResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $mappedResponse['order_id']          = $raw3DAuthResponseData['TURKPOS_RETVAL_Siparis_ID'];
            $mappedResponse['amount']            = $this->valueFormatter->formatAmount(
                $raw3DAuthResponseData['TURKPOS_RETVAL_Tahsilat_Tutari'],
                $txType
            );
            $mappedResponse['currency']          = $this->valueMapper->mapCurrency(
                $raw3DAuthResponseData['TURKPOS_RETVAL_PB'],
                $txType
            );
            $mappedResponse['installment_count'] = isset($raw3DAuthResponseData['TURKPOS_RETVAL_Taksit'])
                ? $this->valueFormatter->formatInstallment($raw3DAuthResponseData['TURKPOS_RETVAL_Taksit'], $txType)
                : null;
            $mappedResponse['masked_number']     = $raw3DAuthResponseData['TURKPOS_RETVAL_KK_No'];
            $mappedResponse['transaction_time']  = $this->valueFormatter->formatDateTime(
                $raw3DAuthResponseData['TURKPOS_RETVAL_Islem_Tarih'],
                $txType
            );
        }

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $result                  = $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);
        $result['payment_model'] = PosInterface::MODEL_3D_HOST;

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;

        $cancelResponse = $rawResponseData['TP_Islem_Iptal_Iade_Kismi2Response']['TP_Islem_Iptal_Iade_Kismi2Result']
            ?? $rawResponseData['TP_Islem_Iptal_OnProvResponse']['TP_Islem_Iptal_OnProvResult'];
        $procReturnCode = $this->getProcReturnCode($cancelResponse);

        if ($procReturnCode > 0) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'order_id'         => null,
            'group_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => null,
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => null,
            'error_code'       => self::TX_APPROVED !== $status ? $procReturnCode : null,
            'error_message'    => self::TX_APPROVED !== $status ? $cancelResponse['Sonuc_Str'] : null,
            'status'           => $status,
            'status_detail'    => null,
            'all'              => $rawResponseData,
        ];
        if (self::TX_APPROVED === $status && isset($rawResponseData['TP_Islem_Iptal_Iade_Kismi2Response'])) {
            $result['auth_code']      = $cancelResponse['Bank_AuthCode'];
            $result['ref_ret_num']    = $cancelResponse['Bank_HostRefNum'];
            $result['transaction_id'] = $cancelResponse['Bank_Trans_ID'];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_STATUS;
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $statusResponse = $rawResponseData['TP_Islem_Sorgulama4Response']['TP_Islem_Sorgulama4Result'];
        $procReturnCode = $this->getProcReturnCode($statusResponse);
        $status         = self::TX_DECLINED;
        if ($procReturnCode > 0) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $dtBilgi = $statusResponse['DT_Bilgi'];

        $defaultResponse['order_id']         = $dtBilgi['Siparis_ID'];
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['transaction_id']   = $dtBilgi['Bank_Trans_ID'];
        $defaultResponse['error_message']    = self::TX_APPROVED === $status ? null : $statusResponse['Sonuc_Str'];
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = null;

        $result = $defaultResponse;
        if (self::TX_APPROVED === $status) {
            $result['auth_code']     = $dtBilgi['Bank_AuthCode'];
            $result['ref_ret_num']   = $dtBilgi['Bank_HostRefNum'];
            $result['masked_number'] = $dtBilgi['KK_No'];
            $result['first_amount']  = $this->valueFormatter->formatAmount($dtBilgi['Toplam_Tutar'], $txType);

            $result['order_status']     = $this->valueMapper->mapOrderStatus($dtBilgi['Durum']);
            $result['transaction_type'] = $this->valueMapper->mapTxType($dtBilgi['Islem_Tip']);

            if (PosInterface::TX_TYPE_PAY_AUTH === $result['transaction_type']) {
                $result['transaction_type'] = PosInterface::TX_TYPE_PAY_AUTH;
                $result['capture_amount']   = $result['first_amount'];
            } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $result['transaction_type']
                && $result['order_status'] === PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED) {
                $result['order_status'] = PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
            }

            $txDate = isset($dtBilgi['Tarih']) ? $this->valueFormatter->formatDateTime($dtBilgi['Tarih'], $txType) : null;
            if ($dtBilgi['Toplam_Iade_Tutar'] > 0) {
                $dtBilgi['refund_amount'] = $this->valueFormatter->formatAmount($dtBilgi['Toplam_Iade_Tutar'], $txType);
                $dtBilgi['refund_time']   = $txDate;
            }

            if (PosInterface::PAYMENT_STATUS_CANCELED === $result['order_status']) {
                $result['cancel_time'] = $txDate;
            }

            $result['capture']          = $result['first_amount'] === $result['capture_amount'];
            $result['capture_time']     = $result['capture'] ? $txDate : null;
            $result['transaction_time'] = $txDate;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $mappedTransactions = [];
        $rawResult          = $rawResponseData['TP_Islem_IzlemeResponse']['TP_Islem_IzlemeResult'];
        $procReturnCode     = $this->getProcReturnCode($rawResult);
        $status             = self::TX_DECLINED;
        if ($procReturnCode > 0) {
            $status = self::TX_APPROVED;
            foreach ($rawResult['DT_Bilgi']['diffgr:diffgram']['NewDataSet']['Temp'] as $rawTx) {
                $mappedTransactions[] = $this->mapSingleHistoryTransaction($rawTx);
            }
        }

        // sort transactions by transaction time
        $mappedTransactions = \array_reverse($mappedTransactions);

        $result = [
            'proc_return_code' => $procReturnCode,
            'error_code'       => null,
            'error_message'    => null,
            'status'           => $status,
            'status_detail'    => null,
            'trans_count'      => \count($mappedTransactions),
            'transactions'     => $mappedTransactions,
            'all'              => $rawResponseData,
        ];

        if (self::TX_APPROVED !== $status) {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $rawResult['Sonuc_Str'];
        }

        return $result;
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
     * @param array<string, mixed> $response
     *
     * @return int|null
     */
    protected function getProcReturnCode(array $response): ?int
    {
        return $response['Sonuc'] ?? $response['TURKPOS_RETVAL_Sonuc'] ?? null;
    }

    /**
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $txType                          = PosInterface::TX_TYPE_HISTORY;
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $procReturnCode                  = $this->getProcReturnCode($rawTx);
        $transaction['proc_return_code'] = $procReturnCode;
        if ($procReturnCode > 0) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $dateTime                        = $this->valueFormatter->formatDateTime($rawTx['Tarih'], $txType);
        $transaction['transaction_type'] = $this->valueMapper->mapTxType($rawTx['Tip_Str']);
        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['currency'] = isset($rawTx['PB'])
                ? $this->valueMapper->mapCurrency($rawTx['PB'], $txType)
                : null;
            $amount                  = null === $rawTx['Tutar']
                ? null : $this->valueFormatter->formatAmount($rawTx['Tutar'], PosInterface::TX_TYPE_HISTORY);
            if (PosInterface::TX_TYPE_PAY_AUTH === $transaction['transaction_type']) {
                $transaction['first_amount']   = $amount;
                $transaction['capture_amount'] = $amount;
                $transaction['capture']        = true;
                $transaction['capture_time']   = $dateTime;
            } elseif (PosInterface::TX_TYPE_CANCEL === $transaction['transaction_type'] && $rawTx['Tutar'] < 0) {
                $transaction['refund_amount'] = $transaction['first_amount'];
            }

            if ($rawTx['Toplam_Iade_Tutar'] > 0) {
                $transaction['refund_amount'] = $this->valueFormatter->formatAmount(
                    $rawTx['Toplam_Iade_Tutar'],
                    PosInterface::TX_TYPE_HISTORY
                );
            }
        } else {
            $transaction['error_code']    = $procReturnCode;
            $transaction['error_message'] = $rawTx['Sonuc_Str'];
        }

        $transaction['order_id']         = $rawTx['ORJ_ORDER_ID'];
        $transaction['payment_model']    = $this->valueMapper->mapSecureType($rawTx['Islem_Guvenlik'], $txType);
        $transaction['transaction_time'] = $dateTime;

        return $transaction;
    }
}
