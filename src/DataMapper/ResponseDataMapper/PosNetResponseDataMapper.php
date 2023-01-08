<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Psr\Log\LogLevel;

class PosNetResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    public const PROCEDURE_SUCCESS_CODE = '1';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        '0'                          => 'declined',
        '2'                          => 'declined',
        '0001'                       => 'bank_call',
        '0005'                       => 'reject',
        '0007'                       => 'bank_call',
        '0012'                       => 'reject',
        '0014'                       => 'reject',
        '0030'                       => 'bank_call',
        '0041'                       => 'reject',
        '0043'                       => 'reject',
        '0051'                       => 'reject',
        '0053'                       => 'bank_call',
        '0054'                       => 'reject',
        '0057'                       => 'reject',
        '0058'                       => 'reject',
        '0062'                       => 'reject',
        '0065'                       => 'reject',
        '0091'                       => 'bank_call',
        '0123'                       => 'transaction_not_found',
        '0444'                       => 'bank_call',
    ];

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $status = self::TX_DECLINED;
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$rawPaymentResponseData]);
        if (empty($rawPaymentResponseData)) {
            return $this->getDefaultPaymentResponse();
        }

        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $errorCode              = $rawPaymentResponseData['respCode'] ?? null;
        $procReturnCode         = $this->getProcReturnCode($rawPaymentResponseData);
        if (
            self::PROCEDURE_SUCCESS_CODE === $procReturnCode
            && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED
            && !$errorCode
        ) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => null,
            'trans_id'         => null,
            'auth_code'        => $rawPaymentResponseData['authCode'] ?? null,
            'ref_ret_num'      => $rawPaymentResponseData['hostlogkey'] ?? null,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($errorCode ?? $procReturnCode),
            'error_code'       => $errorCode,
            'error_message'    => $rawPaymentResponseData['respText'] ?? null,
            'all'              => $rawPaymentResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);
        $status                = self::TX_DECLINED;
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData); //test
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED) {
            $status = self::TX_APPROVED;
        }
        /** @var array<string, string> $oosResolveMerchantDataResponse */
        $oosResolveMerchantDataResponse = $raw3DAuthResponseData['oosResolveMerchantDataResponse'];

        $mdStatus = $oosResolveMerchantDataResponse['mdStatus'];

        $threeDResponse = [
            'order_id'             => $oosResolveMerchantDataResponse['xid'] ?? null,
            'transaction_security' => $this->mapResponseTransactionSecurity($mdStatus),
            'proc_return_code'     => $procReturnCode,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'md_status'            => $mdStatus,
            'md_error_message'     => $oosResolveMerchantDataResponse['mdErrorMessage'] ?? null,
            '3d_all'               => $raw3DAuthResponseData,
        ];
        if (null === $rawPaymentResponseData) {
            $paymentResponseData = $this->getDefaultPaymentResponse();
        } else {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        return $this->mergeArraysPreferNonNullValues($threeDResponse, $paymentResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function map3DPayResponseData($raw3DAuthResponseData): array
    {
        return $this->map3DPaymentData($raw3DAuthResponseData, $raw3DAuthResponseData);
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
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse($rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $errorCode       = $rawResponseData['respCode'] ?? null;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $rawResponseData && !$errorCode) {
            $status = self::TX_APPROVED;
        }

        $state           = $rawResponseData['state'] ?? null;
        $transactionType = null;
        if (null !== $state) {
            $transactionType = $this->mapTxType($state);
        }
        $results = [
            'auth_code'        => null,
            'trans_id'         => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type'          => $transactionType,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $errorCode,
            'error_message'    => $rawResponseData['respText'] ?? null,
            'all'              => $rawResponseData,
        ];

        /** @var array<string, string>|null $transactionDetails */
        $transactionDetails = $rawResponseData['transaction'] ?? null;
        $txResults          = [];
        if (null !== $transactionDetails) {
            $txResults = [
                'auth_code'   => $transactionDetails['authCode'] ?? null,
                'trans_id'    => null,
                'ref_ret_num' => $transactionDetails['hostlogkey'] ?? null,
                'date'        => $transactionDetails['tranDate'] ?? null,
            ];
        }

        return array_merge($results, $txResults);
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $errorCode       = $rawResponseData['respCode'] ?? null;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && isset($rawResponseData['transactions']) && !$errorCode) {
            $status = self::TX_APPROVED;
        }

        $state     = null;
        $txResults = [];

        if (isset($rawResponseData['transactions']['transaction'])) {
            $transactionDetails = $rawResponseData['transactions']['transaction'];

            $state    = $transactionDetails['state'] ?? null;
            $authCode = $transactionDetails['authCode'] ?? null;

            $txResults = [
                'auth_code'   => $authCode,
                'trans_id'    => null,
                'ref_ret_num' => $transactionDetails['hostlogkey'] ?? null,
                'date'        => $transactionDetails['tranDate'] ?? null,
            ];
        }

        $transactionType = null;
        if (null !== $state) {
            $transactionType = $this->mapTxType($state);
        }
        $results = [
            'auth_code'        => null,
            'trans_id'         => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type'          => $transactionType,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $errorCode,
            'error_message'    => $rawResponseData['respText'] ?? null,
            'all'              => $rawResponseData,
        ];

        return array_merge($results, $txResults);
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        $status          = self::TX_DECLINED;
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $errorCode       = $rawResponseData['respCode'] ?? null;
        $procReturnCode  = $this->getProcReturnCode($rawResponseData);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && isset($rawResponseData['transactions']) && !$errorCode) {
            $status = self::TX_APPROVED;
        }

        $state     = null;
        $txResults = [];
        $refunds   = [];
        if (isset($rawResponseData['transactions']['transaction'])) {
            $transactionDetails = $rawResponseData['transactions']['transaction'];

            $state    = $transactionDetails['state'] ?? null;
            $authCode = $transactionDetails['authCode'] ?? null;

            if (is_array($transactionDetails)) {
                if (count($transactionDetails) > 0) {
                    $state    = $transactionDetails[0]['state'];
                    $authCode = $transactionDetails[0]['authCode'];
                }
                if (count($transactionDetails) > 1) {
                    foreach ($transactionDetails as $key => $_transaction) {
                        if ($key > 0) {
                            $currency  = $this->mapCurrency($_transaction['currencyCode']);
                            $refunds[] = [
                                'amount'    => (float) $_transaction['amount'],
                                'currency'  => $currency,
                                'auth_code' => $_transaction['authCode'],
                                'date'      => $_transaction['tranDate'],
                            ];
                        }
                    }
                }
            }

            $txResults = [
                'auth_code'   => $authCode,
                'trans_id'    => null,
                'ref_ret_num' => $transactionDetails['hostlogkey'] ?? null,
                'date'        => $transactionDetails['tranDate'] ?? null,
            ];
        }

        $transactionType = null;
        if (null !== $state) {
            $transactionType = $this->mapTxType($state);
        }

        $results = [
            'auth_code'        => null,
            'trans_id'         => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type'          => $transactionType,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $errorCode,
            'error_message'    => $rawResponseData['respText'] ?? null,
            'refunds'          => $refunds,
            'all'              => $rawResponseData,
        ];

        return array_merge($results, $txResults);
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
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['approved'] ?? null;
    }
}
