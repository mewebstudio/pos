<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

/**
 * maps the response of AkOde API requests
 */
class AkOdePosResponseDataMapper extends AbstractResponseDataMapper
{
    public const PROCEDURE_SUCCESS_CODE = '00';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        0 => self::TX_APPROVED,
        101 => 'transaction_not_found',
        998 => 'invalid_transaction',
        999 => 'general_error',
    ];

    /**
     * Order Status Codes
     *
     * @var array<int, string>
     */
    protected array $orderStatusMappings = [
        0  => PosInterface::PAYMENT_STATUS_ERROR,
        1  => PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED,
        2  => PosInterface::PAYMENT_STATUS_CANCELED,
        3  => PosInterface::PAYMENT_STATUS_PARTIALLY_REFUNDED,
        4  => PosInterface::PAYMENT_STATUS_FULLY_REFUNDED,
        5  => PosInterface::PAYMENT_STATUS_PRE_AUTH_COMPLETED,
    ];

    /**
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string>       $txTypeMappings
     * @param LoggerInterface                         $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, LoggerInterface $logger)
    {
        parent::__construct($currencyMappings, $txTypeMappings, $logger);
    }

    /**
     * @param int $txType
     *
     * @return string
     */
    public function mapTxType($txType): ?string
    {
        if (0 === $txType) {
            return null;
        }

        return parent::mapTxType($txType);
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse();
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
            'trans_id'         => $rawPaymentResponseData['TransactionId'],
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
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData): array
    {
        $status = self::TX_DECLINED;

        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $procReturnCode    = $raw3DAuthResponseData['BankResponseCode'];
        $transactionStatus = $this->orderStatusMappings[$raw3DAuthResponseData['RequestStatus']] ?? null;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $transactionStatus
            && \in_array($raw3DAuthResponseData['MdStatus'], ['1', '2', '3', '4'])
        ) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse();

        $response = [
            'order_id'             => $raw3DAuthResponseData['OrderId'],
            'transaction_type'     => null,
            'transaction_security' => $this->mapResponseTransactionSecurity($raw3DAuthResponseData['MdStatus']),
            'md_status'            => $raw3DAuthResponseData['MdStatus'],
            'status'               => $status,
            'proc_return_code'     => $procReturnCode,
            'tx_status'            => $transactionStatus,
            'md_error_message'     => null,
            'all'                  => $raw3DAuthResponseData,
        ];

        if (self::TX_APPROVED !== $status) {
            $response['error_message'] = $raw3DAuthResponseData['BankResponseMessage'];
            $response['error_code']    = $procReturnCode;
        }

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $response);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DHostResponseData(array $raw3DAuthResponseData): array
    {
        return $this->map3DPayResponseData($raw3DAuthResponseData);
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
            'trans_id'         => $rawResponseData['TransactionId'],
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
            'trans_id'         => $rawResponseData['TransactionId'],
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
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        $result = [
            'order_id'         => $rawResponseData['OrderId'],
            'auth_code'        => $rawResponseData['AuthCode'],
            'proc_return_code' => $procReturnCode,
            'trans_id'         => null,
            'trans_date'       => null,
            'error_message'    => $rawResponseData['BankResponseMessage'],
            'ref_ret_num'      => null,
            'masked_number'    => $rawResponseData['CardNo'],
            'order_status'     => $this->orderStatusMappings[$rawResponseData['RequestStatus']] ?? $rawResponseData['RequestStatus'],
            'transaction_type' => $this->mapTxType($rawResponseData['TransactionType']),
            'capture_amount'   => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'capture'          => false,
            'all'              => $rawResponseData,
        ];
        if (self::TX_APPROVED === $status) {
            $result['trans_id']       = $rawResponseData['TransactionId'] > 0 ? $rawResponseData['TransactionId'] : null;
            $result['trans_date']     = $rawResponseData['TransactionDate'] > 0 ? $rawResponseData['TransactionDate'] : null;
            $result['currency']       = $this->mapCurrency($rawResponseData['Currency']);
            $result['first_amount']   = $this->formatAmount($rawResponseData['Amount']);
            $result['capture_amount'] = $this->formatAmount($rawResponseData['NetAmount']);
            $result['capture']        = $result['first_amount'] === $result['capture_amount'];
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $errorCode       = $rawResponseData['Code'];
        $status          = self::TX_DECLINED;
        if (0 === $errorCode) {
            $status = self::TX_APPROVED;
        }

        $mappedTransactions = [];
        $orderId = null;
        if (self::TX_APPROVED === $status) {
            foreach ($rawResponseData['Transactions'] as $transaction) {
                $mappedTransaction = $this->mapStatusResponse($transaction);
                unset($mappedTransaction['all']);
                $mappedTransactions[] = $mappedTransaction;
                $orderId = $mappedTransaction['order_id'];
            }
        }

        return [
            'order_id'         => $orderId,
            'proc_return_code' => null,
            'error_code'       => self::TX_DECLINED === $status ? $errorCode : null,
            'error_message'    => self::TX_DECLINED === $status ? ($rawResponseData['message'] ?? $rawResponseData['Message']) : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode),
            'transactions'     => $mappedTransactions,
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
     * "100001" => 1000.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((float) $amount) / 100;
    }
}
