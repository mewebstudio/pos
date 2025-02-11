<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

class ParamPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
    ];

    /**
     * @var array<string, PosInterface::PAYMENT_STATUS_*>
     */
    protected array $orderStatusMappings = [
        'FAIL'           => PosInterface::PAYMENT_STATUS_ERROR,
        'BANK_FAIL'      => PosInterface::PAYMENT_STATUS_ERROR,
        'SUCCESS'        => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        'CANCEL'         => PosInterface::PAYMENT_STATUS_CANCELED,
        'REFUND'         => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        'PARTIAL_REFUND' => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $statusRequestTxMappings = [
        'SALE'      => PosInterface::TX_TYPE_PAY_AUTH,
        'PRE_AUTH'  => PosInterface::TX_TYPE_PAY_PRE_AUTH,
        'POST_AUTH' => PosInterface::TX_TYPE_PAY_POST_AUTH,
    ];

    /**
     * @var array<string, PosInterface::TX_TYPE_*>
     */
    private array $historyRequestTxMappings = [
        'Satış' => PosInterface::TX_TYPE_PAY_AUTH,
        'İptal' => PosInterface::TX_TYPE_CANCEL,
        'İade'  => PosInterface::TX_TYPE_REFUND,
    ];

    /**
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string>  $txTypeMappings
     * @param array<PosInterface::MODEL_*, string>    $secureTypeMappings
     * @param LoggerInterface                         $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, array $secureTypeMappings, LoggerInterface $logger)
    {
        parent::__construct($currencyMappings, $txTypeMappings, $secureTypeMappings, $logger);

        $this->secureTypeMappings = [
            'NONSECURE' => PosInterface::MODEL_NON_SECURE,
            '3D'        => PosInterface::MODEL_3D_SECURE,
        ];

        $this->currencyMappings = [
            'TRL' => PosInterface::CURRENCY_TRY,
            'TL'  => PosInterface::CURRENCY_TRY,
            'EUR' => PosInterface::CURRENCY_EUR,
            'USD' => PosInterface::CURRENCY_USD,
        ];
    }

    /**
     * @inheritDoc
     */
    public function mapTxType($txType): ?string
    {
        return $this->statusRequestTxMappings[$txType]
            ?? $this->historyRequestTxMappings[$txType]
            ?? parent::mapTxType($txType);
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
            $mappedResponse['transaction_time'] = new \DateTimeImmutable();
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
            $mappedResponse['transaction_time'] = new \DateTimeImmutable();
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
            'amount'               => null !== $raw3DAuthResponseData['transactionAmount'] ? $this->formatAmount($raw3DAuthResponseData['transactionAmount']) : null,
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
            $mappedResponse['amount']            = $this->formatAmount($raw3DAuthResponseData['TURKPOS_RETVAL_Tahsilat_Tutari']);
            $mappedResponse['currency']          = $this->mapCurrency($raw3DAuthResponseData['TURKPOS_RETVAL_PB']);
            $mappedResponse['installment_count'] = isset($raw3DAuthResponseData['TURKPOS_RETVAL_Taksit']) ? $this->mapInstallment($raw3DAuthResponseData['TURKPOS_RETVAL_Taksit']) : null;
            $mappedResponse['masked_number']     = $raw3DAuthResponseData['TURKPOS_RETVAL_KK_No'];
            $mappedResponse['transaction_time']  = new \DateTimeImmutable($raw3DAuthResponseData['TURKPOS_RETVAL_Islem_Tarih']);
        }

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
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
            $result['first_amount']  = (float) $dtBilgi['Toplam_Tutar'];

            $result['order_status']     = $this->orderStatusMappings[$dtBilgi['Durum']] ?? null;
            $result['transaction_type'] = $this->mapTxType($dtBilgi['Islem_Tip']);

            if (PosInterface::TX_TYPE_PAY_AUTH === $result['transaction_type']) {
                $result['transaction_type'] = PosInterface::TX_TYPE_PAY_AUTH;
                $result['capture_amount']   = $result['first_amount'];
            } elseif (PosInterface::TX_TYPE_PAY_PRE_AUTH === $result['transaction_type']
                && $result['order_status'] === PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED) {
                $result['order_status'] = PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED;
            }

            $txDate = isset($dtBilgi['Tarih']) ? new \DateTimeImmutable($dtBilgi['Tarih']) : null;
            if ($dtBilgi['Toplam_Iade_Tutar'] > 0) {
                $dtBilgi['refund_amount'] = $this->formatAmount($dtBilgi['Toplam_Iade_Tutar']);
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
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((float) \str_replace(',', '.', $amount));
    }

    /**
     * @param array<int, string> $rawTx
     *
     * @return array<string, string|int|float|null>
     */
    private function mapSingleHistoryTransaction(array $rawTx): array
    {
        $rawTx                           = $this->emptyStringsToNull($rawTx);
        $transaction                     = $this->getDefaultOrderHistoryTxResponse();
        $procReturnCode                  = $this->getProcReturnCode($rawTx);
        $transaction['proc_return_code'] = $procReturnCode;
        if ($procReturnCode > 0) {
            $transaction['status'] = self::TX_APPROVED;
        }

        $transaction['transaction_type'] = $this->mapTxType($rawTx['Tip_Str']);
        if (self::TX_APPROVED === $transaction['status']) {
            $transaction['currency'] = isset($rawTx['PB']) ? $this->mapCurrency($rawTx['PB']) : null;
            $amount                  = null === $rawTx['Tutar'] ? null : $this->formatAmount($rawTx['Tutar']);
            if (PosInterface::TX_TYPE_PAY_AUTH === $transaction['transaction_type']) {
                $transaction['first_amount']   = $amount;
                $transaction['capture_amount'] = $amount;
                $transaction['capture']        = true;
                $transaction['capture_time']   = new \DateTimeImmutable($rawTx['Tarih']);
            } elseif (PosInterface::TX_TYPE_CANCEL === $transaction['transaction_type'] && $rawTx['Tutar'] < 0) {
                $transaction['refund_amount'] = $transaction['first_amount'];
            }

            if ($rawTx['Toplam_Iade_Tutar'] > 0) {
                $transaction['refund_amount'] = $this->formatAmount($rawTx['Toplam_Iade_Tutar']);
            }
        } else {
            $transaction['error_code']    = $procReturnCode;
            $transaction['error_message'] = $rawTx['Sonuc_Str'];
        }

        $transaction['order_id']         = $rawTx['ORJ_ORDER_ID'];
        $transaction['payment_model']    = $this->mapSecurityType($rawTx['Islem_Guvenlik']);
        $transaction['transaction_time'] = new \DateTimeImmutable($rawTx['Tarih']);

        return $transaction;
    }
}
