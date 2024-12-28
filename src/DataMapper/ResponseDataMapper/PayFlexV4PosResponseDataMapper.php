<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

class PayFlexV4PosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '0000';

    /**
     * Response Codes
     *
     * @var array<string|int, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        '0312'                       => 'reject',
        '1083'                       => 'invalid_transaction',
        '1059'                       => 'invalid_transaction',
        '9039'                       => 'invalid_credentials',
        '9065'                       => 'invalid_credentials',
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

        $this->secureTypeMappings += [
            '1' => PosInterface::MODEL_NON_SECURE,
            '2' => PosInterface::MODEL_3D_SECURE,
            '3' => PosInterface::MODEL_3D_PAY,
        ];
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
        $mdStatus              = $this->extractMdStatus($raw3DAuthResponseData);
        $threeDAuthStatus      = $this->is3dAuthSuccess($mdStatus) ? self::TX_APPROVED : self::TX_DECLINED;

        if (self::TX_APPROVED === $threeDAuthStatus && null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData, $txType, $order);
        } else {
            $paymentResponseData = $this->getDefaultPaymentResponse($txType, null);
        }

        $threeDResponse = [
            'eci'                  => $raw3DAuthResponseData['Eci'],
            'cavv'                 => $raw3DAuthResponseData['Cavv'],
            'auth_code'            => null,
            'order_id'             => $raw3DAuthResponseData['VerifyEnrollmentRequestId'],
            'status'               => $threeDAuthStatus,
            'currency'             => $paymentResponseData['currency'] ?? $this->mapCurrency($raw3DAuthResponseData['PurchCurrency']),
            'installment_count'    => $paymentResponseData['installment_count'] ?? $this->mapInstallment($raw3DAuthResponseData['InstallmentCount']),
            'status_detail'        => null,
            'error_code'           => self::TX_DECLINED === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorCode'] : null,
            'error_message'        => self::TX_DECLINED === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorMessage'] : null,
            'md_status'            => $mdStatus,
            'md_error_message'     => self::TX_DECLINED === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorMessage'] : null,
            'transaction_security' => null,
            'all'                  => $rawPaymentResponseData,
            '3d_all'               => $raw3DAuthResponseData,
        ];

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
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
     * {@inheritdoc}
     */
    public function mapCancelResponse($rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $resultCode      = $this->getProcReturnCode($rawResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['TransactionId'] ?? null,
            'auth_code'        => (self::TX_DECLINED !== $status) ? $rawResponseData['AuthCode'] : null,
            'ref_ret_num'      => $rawResponseData['Rrn'] ?? null,
            'proc_return_code' => $resultCode,
            'transaction_id'   => $rawResponseData['TransactionId'] ?? null,
            'error_code'       => (self::TX_DECLINED === $status) ? $resultCode : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawResponseData['ResultDetail'] : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($resultCode),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @param array{TransactionSearchResultInfo: null|array{TransactionSearchResultInfo: array<string, string>}, ResponseInfo: array{ResponseCode: string, ResponseMessage: string, ResponseDateTime: string, Status: 'Success'|'Error'}} $rawResponseData
     *
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        /**
         * @var array{ResponseCode: string, ResponseMessage: string, ResponseDateTime: string, Status: 'Success'|'Error'} $responseInfo
         */
        $responseInfo   = $rawResponseData['ResponseInfo'];
        $procReturnCode = $responseInfo['ResponseCode'];

        $defaultResponse                     = $this->getDefaultStatusResponse($rawResponseData);
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $status                              = self::TX_DECLINED;
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['status']        = $status;
        $defaultResponse['status_detail'] = $this->getStatusDetail($procReturnCode);
        if (self::TX_DECLINED === $status) {
            $defaultResponse['error_code']    = $procReturnCode;
            $defaultResponse['error_message'] = $responseInfo['ResponseMessage'];

            return $defaultResponse;
        }

        $txResultInfo  = $rawResponseData['TransactionSearchResultInfo']['TransactionSearchResultInfo'];
        $orderProcCode = $this->getProcReturnCode($txResultInfo);

        $orderStatus = PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED;
        if ('true' === $txResultInfo['IsCanceled']) {
            $orderStatus = PosInterface::PAYMENT_STATUS_CANCELED;
        } elseif ('true' === $txResultInfo['IsReversed']) {
            $orderStatus = 'REVERSED';
        } elseif ('true' === $txResultInfo['IsRefunded']) {
            $orderStatus = PosInterface::PAYMENT_STATUS_FULLY_REFUNDED;
        }

        $defaultResponse['order_id']         = $txResultInfo['OrderId'];
        $defaultResponse['auth_code']        = $txResultInfo['AuthCode'];
        $defaultResponse['transaction_id']   = $txResultInfo['TransactionId'];
        $defaultResponse['ref_ret_num']      = $txResultInfo['Rrn'];
        $defaultResponse['order_status']     = $orderStatus;
        $defaultResponse['transaction_type'] = $this->mapTxType($txResultInfo['TransactionType']);
        $defaultResponse['currency']         = $this->mapCurrency($txResultInfo['AmountCode']);
        $defaultResponse['first_amount']     = $this->formatAmount($txResultInfo['CurrencyAmount'] ?? $txResultInfo['Amount']);
        $defaultResponse['capture_amount']   = null;
        $defaultResponse['status']           = self::PROCEDURE_SUCCESS_CODE === $orderProcCode ? self::TX_APPROVED : self::TX_DECLINED;
        $defaultResponse['error_code']       = self::PROCEDURE_SUCCESS_CODE !== $orderProcCode ? $txResultInfo['HostResultCode'] : null;
        $defaultResponse['error_message']    = self::PROCEDURE_SUCCESS_CODE !== $orderProcCode ? $txResultInfo['ResponseMessage'] : null;

        return $defaultResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $rawPaymentResponseData             = $this->emptyStringsToNull($rawPaymentResponseData);
        $commonResponse                     = $this->getCommonPaymentResponse($rawPaymentResponseData, $txType);
        $commonResponse['order_id']         = $rawPaymentResponseData['OrderId'] ?? null;
        $commonResponse['currency']         = isset($rawPaymentResponseData['CurrencyCode']) ? $this->mapCurrency($rawPaymentResponseData['CurrencyCode']) : null;
        $commonResponse['amount']           = isset($rawPaymentResponseData['TLAmount']) ? $this->formatAmount($rawPaymentResponseData['TLAmount']) : null;
        $commonResponse['transaction_type'] = isset($rawPaymentResponseData['TransactionType']) ? $this->mapTxType($rawPaymentResponseData['TransactionType']) : null;

        if (self::TX_APPROVED === $commonResponse['status']) {
            $commonResponse['transaction_id']   = $rawPaymentResponseData['TransactionId'];
            $txTime                             = $rawPaymentResponseData['HostDate'];
            if (\strlen($txTime) === 10) { // ziraat is sending host date without year
                $txTime = date('Y').$txTime;
            }

            $commonResponse['transaction_time'] = new \DateTimeImmutable($txTime);
            $commonResponse['auth_code']        = $rawPaymentResponseData['AuthCode'];
            $commonResponse['ref_ret_num']      = $rawPaymentResponseData['TransactionId'];
            $commonResponse['batch_num']        = $rawPaymentResponseData['BatchNo'];
        }

        $this->logger->debug('mapped payment response', $commonResponse);

        return $commonResponse;
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function is3dAuthSuccess(?string $mdStatus): bool
    {
        /**
         * Y => 3D secure
         * A => Half 3D secure
         */
        return 'Y' === $mdStatus;
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $raw3DAuthResponseData['Status'] ?? null;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected function mapResponseTransactionSecurity(string $mdStatus): string
    {
        return 'MPI fallback';
    }

    /**
     * Get ProcReturnCode
     *
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['ResultCode'] ?? null;
    }

    /**
     * @param string|null $procReturnCode
     *
     * @return string|null
     */
    protected function getStatusDetail(?string $procReturnCode): ?string
    {
        return $this->codes[$procReturnCode] ?? null;
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     *
     * @param array<string, string> $responseData
     * @param string                $txType
     *
     * @return array<string, string>
     */
    private function getCommonPaymentResponse(array $responseData, string $txType): array
    {
        $status     = self::TX_DECLINED;
        $resultCode = $this->getProcReturnCode($responseData);
        if (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        }

        $paymentModel = isset($responseData['ThreeDSecureType']) ? $this->mapSecurityType($responseData['ThreeDSecureType']) : null;
        $response     = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $response['proc_return_code'] = $resultCode;
        $response['status']           = $status;
        $response['status_detail']    = $this->getStatusDetail($resultCode);
        $response['error_code']       = (self::TX_DECLINED === $status) ? $resultCode : null;
        $response['error_message']    = (self::TX_DECLINED === $status) ? $responseData['ResultDetail'] : null;
        $response['all']              = $responseData;

        return $response;
    }
}
