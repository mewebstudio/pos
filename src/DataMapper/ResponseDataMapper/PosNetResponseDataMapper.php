<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

class PosNetResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '1';

    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
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
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_NON_SECURE);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
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

        $defaultResponse['order_id']         = $order['id'];
        $defaultResponse['currency']         = $order['currency'];
        $defaultResponse['amount']           = $order['amount'];
        $defaultResponse['auth_code']        = $rawPaymentResponseData['authCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawPaymentResponseData['hostlogkey'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($errorCode ?? $procReturnCode);
        $defaultResponse['error_code']       = $errorCode;
        $defaultResponse['error_message']    = $rawPaymentResponseData['respText'] ?? null;
        $defaultResponse['all']              = $rawPaymentResponseData;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['installment_count'] = $this->mapInstallment($rawPaymentResponseData['instInfo']['inst1']);
            $defaultResponse['transaction_time']  = new \DateTimeImmutable();
        }

        return $defaultResponse;
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
        $status                = self::TX_DECLINED;
        $procReturnCode        = $this->getProcReturnCode($raw3DAuthResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode && $this->getStatusDetail($procReturnCode) === self::TX_APPROVED) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse = $this->getDefaultPaymentResponse($txType, PosInterface::MODEL_3D_SECURE);

        if (!isset($raw3DAuthResponseData['oosResolveMerchantDataResponse'])) {
            $defaultResponse['proc_return_code'] = $procReturnCode;
            $defaultResponse['error_code']       = $raw3DAuthResponseData['respCode'];
            $defaultResponse['error_message']    = $raw3DAuthResponseData['respText'];
            $defaultResponse['3d_all']           = $raw3DAuthResponseData;

            return $defaultResponse;
        }

        /** @var array<string, string|null> $oosResolveMerchantDataResponse */
        $oosResolveMerchantDataResponse = $raw3DAuthResponseData['oosResolveMerchantDataResponse'];

        $mdStatus            = $this->extractMdStatus($raw3DAuthResponseData);
        $transactionSecurity = null;
        if (null === $mdStatus) {
            $this->logger->error('mdStatus boş döndü. Sağlanan banka API bilgileri eksik/yanlış olabilir.');
        } else {
            $transactionSecurity = $this->mapResponseTransactionSecurity($mdStatus);
        }

        $threeDResponse = [
            'order_id'             => $order['id'],
            'remote_order_id'      => $oosResolveMerchantDataResponse['xid'] ?? null,
            'transaction_security' => $transactionSecurity,
            'amount'               => $this->formatAmount((string) $oosResolveMerchantDataResponse['amount']),
            'currency'             => $this->mapCurrency((string) $oosResolveMerchantDataResponse['currency']),
            'proc_return_code'     => $procReturnCode,
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail($procReturnCode),
            'md_status'            => $mdStatus,
            'md_error_message'     => $oosResolveMerchantDataResponse['mdErrorMessage'] ?? null,
            '3d_all'               => $raw3DAuthResponseData,
        ];
        if (null === $rawPaymentResponseData) {
            $paymentResponseData = $defaultResponse;
        } else {
            $paymentResponseData = $this->map3dPaymentResponseCommon(
                $rawPaymentResponseData,
                $txType,
                PosInterface::MODEL_3D_SECURE
            );
        }

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
            'transaction_id'   => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type' => $transactionType,
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
                'transaction_id'    => null,
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

        $txResults = [];

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        if (isset($rawResponseData['transactions']['transaction'])) {
            $transactionDetails = $rawResponseData['transactions']['transaction'];

            $txResults = [
                'currency'         => $this->mapCurrency($transactionDetails['currencyCode']),
                'first_amount'     => $this->formatStatusAmount($transactionDetails['amount']),
                'transaction_type' => null === $transactionDetails['state'] ? null : $this->mapTxType($transactionDetails['state']),
                'order_id'         => $transactionDetails['orderID'],
                'auth_code'        => $transactionDetails['authCode'] ?? null,
                'ref_ret_num'      => $transactionDetails['hostlogkey'] ?? null,
                // tranDate ex: 2019-10-10 11:21:14.281
                'transaction_time' => isset($transactionDetails['tranDate']) ? new \DateTimeImmutable($transactionDetails['tranDate']) : null,
            ];
        }

        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($procReturnCode);
        $defaultResponse['error_code']       = self::TX_APPROVED !== $status ? $errorCode : null;
        $defaultResponse['error_message']    = self::TX_APPROVED !== $status ? ($rawResponseData['respText'] ?? null) : null;

        return $this->mergeArraysPreferNonNullValues($defaultResponse, $txResults);
    }

    /**
     * todo refactor
     * {@inheritDoc}
     */
    public function mapOrderHistoryResponse(array $rawResponseData): array
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
                if ([] !== $transactionDetails) {
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
                'transaction_id'    => null,
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
            'transaction_id'   => null,
            'ref_ret_num'      => null,
            'group_id'         => null,
            'date'             => null,
            'transaction_type' => $transactionType,
            'proc_return_code' => $procReturnCode,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail($procReturnCode),
            'error_code'       => $errorCode,
            'error_message'    => $rawResponseData['respText'] ?? null,
            'refunds'          => $refunds,
            'all'              => $rawResponseData,
        ];

        return \array_merge($results, $txResults);
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
        return $raw3DAuthResponseData['oosResolveMerchantDataResponse']['mdStatus'] ?? null;
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
     * @param array<string, string> $response
     *
     * @return string|null
     */
    protected function getProcReturnCode(array $response): ?string
    {
        return $response['approved'] ?? null;
    }

    /**
     * "100001" => 1000.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return ((int) $amount) / 100;
    }

    /**
     * "1,16" => 1.16
     * @param string $amount
     *
     * @return float
     */
    protected function formatStatusAmount(string $amount): float
    {
        return (float) \str_replace(',', '.', \str_replace('.', '', $amount));
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, mixed> $rawPaymentResponseData
     * @param string               $txType
     * @param string               $paymentModel
     *
     * @return array<string, mixed>
     */
    private function map3dPaymentResponseCommon(array $rawPaymentResponseData, string $txType, string $paymentModel): array
    {
        $status = self::TX_DECLINED;
        $this->logger->debug('mapping payment response', [$rawPaymentResponseData]);
        $defaultResponse = $this->getDefaultPaymentResponse($txType, $paymentModel);
        if ([] === $rawPaymentResponseData) {
            return $defaultResponse;
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

        $defaultResponse['auth_code']        = $rawPaymentResponseData['authCode'] ?? null;
        $defaultResponse['ref_ret_num']      = $rawPaymentResponseData['hostlogkey'] ?? null;
        $defaultResponse['proc_return_code'] = $procReturnCode;
        $defaultResponse['status']           = $status;
        $defaultResponse['status_detail']    = $this->getStatusDetail($errorCode ?? $procReturnCode);
        $defaultResponse['error_code']       = $errorCode;
        $defaultResponse['error_message']    = $rawPaymentResponseData['respText'] ?? null;
        $defaultResponse['all']              = $rawPaymentResponseData;
        if (self::TX_APPROVED === $status) {
            $defaultResponse['installment_count'] = $this->mapInstallment($rawPaymentResponseData['instInfo']['inst1']);
            $defaultResponse['transaction_time']  = new \DateTimeImmutable();
        }

        return $defaultResponse;
    }
}
