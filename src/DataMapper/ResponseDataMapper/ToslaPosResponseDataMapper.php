<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * maps the response of Tosla API requests
 */
class ToslaPosResponseDataMapper extends AbstractResponseDataMapper
{
    public const PROCEDURE_SUCCESS_CODE = '00';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        0                            => self::TX_APPROVED,
        101                          => 'transaction_not_found',
        998                          => 'invalid_transaction',
        999                          => 'general_error',
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
        $errorCode      = $rawPaymentResponseData['Code'];
        $status         = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $mappedResponse = [
            'order_id'         => $rawPaymentResponseData['OrderId'],
            'currency'         => $order['currency'],
            'amount'           => $order['amount'],
            'transaction_id'   => $rawPaymentResponseData['TransactionId'],
            'transaction_time' => self::TX_APPROVED === $status ? $this->valueFormatter->formatDateTime('now', $txType) : null,
            'transaction_type' => null,
            'ref_ret_num'      => $rawPaymentResponseData['HostReferenceNumber'],
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'error_code'       => self::TX_APPROVED === $status ? null : $procReturnCode,
            'error_message'    => self::TX_APPROVED === $status ? null : $rawPaymentResponseData['Message'],
            'all'              => $rawPaymentResponseData,
        ];

        $this->logger->debug('mapped payment response', $mappedResponse);

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $mappedResponse);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $status = self::TX_DECLINED;

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $procReturnCode    = $raw3DAuthResponseData['BankResponseCode'];
        $mdStatus          = $this->extractMdStatus($raw3DAuthResponseData);
        $transactionStatus = $this->valueMapper->mapOrderStatus($raw3DAuthResponseData['RequestStatus']);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transactionStatus
            && $this->is3dAuthSuccess($mdStatus)
        ) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_PAY);

        $response = [
            'order_id'             => $raw3DAuthResponseData['OrderId'],
            'transaction_type'     => null,
            'transaction_security' => null === $mdStatus ? null : $this->mapResponseTransactionSecurity($mdStatus),
            'currency'             => $order['currency'],
            'amount'               => $order['amount'],
            'md_status'            => $mdStatus,
            'status'               => $status,
            'proc_return_code'     => $procReturnCode,
            'tx_status'            => $transactionStatus,
            'md_error_message'     => null,
            'all'                  => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED !== $status) {
            $response['error_message'] = $raw3DAuthResponseData['BankResponseMessage'];
            $response['error_code']    = $procReturnCode;
        } else {
            $response['transaction_time'] = $this->valueFormatter->formatDateTime('now', $txType);
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $response);
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
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => $rawResponseData['HostReferenceNumber'],
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => $rawResponseData['TransactionId'],
            'error_code'       => self::TX_DECLINED === $status ? $errorCode : null,
            'error_message'    => self::TX_DECLINED === $status ? $rawResponseData['Message'] : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['OrderId'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'ref_ret_num'      => $rawResponseData['HostReferenceNumber'],
            'proc_return_code' => $procReturnCode,
            'transaction_id'   => $rawResponseData['TransactionId'],
            'error_code'       => self::TX_DECLINED === $status ? $errorCode : null,
            'error_message'    => self::TX_DECLINED === $status ? $rawResponseData['Message'] : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_STATUS;
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['order_id']         = $rawResponseData['OrderId'];
        $defaultResponse['auth_code']        = $rawResponseData['AuthCode'];
        $defaultResponse['transaction_id']   = $rawResponseData['TransactionId'] > 0 ? $rawResponseData['TransactionId'] : null;
        $defaultResponse['masked_number']    = $rawResponseData['CardNo'];
        $defaultResponse['order_status']     = $this->valueMapper->mapOrderStatus($rawResponseData['RequestStatus']);
        $defaultResponse['transaction_type'] = $this->valueMapper->mapTxType($rawResponseData['TransactionType']);
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($errorCode);

        $isPaymentTransaction = \in_array(
            $defaultResponse['transaction_type'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH],
            true,
        );

        if (self::TX_APPROVED === $status) {
            $defaultResponse['installment_count'] = $rawResponseData['InstallmentCount'];
            if ($rawResponseData['Currency'] > 0) {
                $defaultResponse['currency'] = $this->valueMapper->mapCurrency($rawResponseData['Currency'], $txType);
                // ex: 20231209154531
                $defaultResponse['transaction_time'] = $this->valueFormatter->formatDateTime($rawResponseData['CreateDate'], $txType);
                $defaultResponse['first_amount']     = $this->valueFormatter->formatAmount($rawResponseData['Amount'], $txType);
            }

            $defaultResponse['refund_amount'] = $rawResponseData['RefundedAmount'] > 0 ? $this->valueFormatter->formatAmount($rawResponseData['RefundedAmount'], $txType) : null;

            if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $isPaymentTransaction) {
                $defaultResponse['capture_amount'] = $defaultResponse['first_amount'];
                $defaultResponse['capture']        = $defaultResponse['first_amount'] <= $defaultResponse['capture_amount'];
                $defaultResponse['capture_time']   = $defaultResponse['transaction_time'];
            }
        } else {
            $defaultResponse['error_message'] = $rawResponseData['BankResponseMessage'];
        }

        return $defaultResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        $mappedTransactions = [];
        $orderId            = null;
        if (self::TX_APPROVED === $status) {
            foreach ($rawResponseData['Transactions'] as $transaction) {
                $mappedTransaction    = $this->mapSingleHistoryResponse($transaction);
                $mappedTransactions[] = $mappedTransaction;
                $orderId              = $mappedTransaction['order_id'];
            }
        }

        return [
            'order_id'         => $orderId,
            'proc_return_code' => null,
            'error_code'       => self::TX_DECLINED === $status ? $errorCode : null,
            'error_message'    => self::TX_DECLINED === $status ? ($rawResponseData['message'] ?? $rawResponseData['Message']) : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'trans_count'      => self::TX_APPROVED === $status ? \count($rawResponseData['Transactions']) : 0,
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
        return $mdStatus === '1';
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['MdStatus'] ?? null;
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
     * @param string|null $code
     *
     * @return string|null
     */
    protected function getStatusDetail(?string $code): ?string
    {
        return $this->codes[$code] ?? null;
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
        return $response['BankResponseCode'] ?? null;
    }

    /**
     * @param array<string, mixed> $rawResponseData
     *
     * @return array<string, mixed>
     */
    public function mapSingleHistoryResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_HISTORY;
        $procReturnCode = $this->getProcReturnCode($rawResponseData);
        $errorCode      = $rawResponseData['Code'];
        $status         = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultOrderHistoryTxResponse();

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['order_id']         = $rawResponseData['OrderId'];
        $defaultResponse['auth_code']        = $rawResponseData['AuthCode'];
        $defaultResponse['transaction_id']   = $rawResponseData['TransactionId'] > 0 ? $rawResponseData['TransactionId'] : null;
        $defaultResponse['masked_number']    = $rawResponseData['CardNo'];
        $defaultResponse['order_status']     = $this->valueMapper->mapOrderStatus($rawResponseData['RequestStatus']);
        $defaultResponse['transaction_type'] = $this->valueMapper->mapTxType($rawResponseData['TransactionType']);
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($errorCode);

        $isPaymentTransaction = \in_array(
            $defaultResponse['transaction_type'],
            [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_POST_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH],
            true,
        );

        if (self::TX_APPROVED === $status) {
            if ($rawResponseData['Currency'] > 0) {
                $defaultResponse['currency'] = $this->valueMapper->mapCurrency($rawResponseData['Currency'], $txType);
                // ex: 20231209154531
                $defaultResponse['transaction_time'] = $this->valueFormatter->formatDateTime($rawResponseData['CreateDate'], $txType);
                $defaultResponse['first_amount']     = $this->valueFormatter->formatAmount($rawResponseData['Amount'], $txType);
            }

            if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $isPaymentTransaction) {
                $captureAmount                     = (float) $rawResponseData['MerchantCommissionAmount'] + (float) $rawResponseData['NetAmount'];
                $defaultResponse['capture_amount'] = $this->valueFormatter->formatAmount((string) $captureAmount, $txType);
                $defaultResponse['capture']        = $defaultResponse['first_amount'] <= $defaultResponse['capture_amount'];
                $defaultResponse['capture_time']   = $defaultResponse['transaction_time'];
            }
        } else {
            $defaultResponse['error_message'] = $rawResponseData['BankResponseMessage'];
        }

        return $defaultResponse;
    }
}
