<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Psr\Log\LogLevel;

class VakifBankPosResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    public const PROCEDURE_SUCCESS_CODE = '0000';

    /**
     * Response Codes
     *
     * @var array<string, string>
     */
    protected $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
    ];

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response'   => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $threeDAuthStatus    = ('Y' === $raw3DAuthResponseData['Status']) ? self::TX_APPROVED : self::TX_DECLINED;
        $paymentResponseData = [];

        if (self::TX_APPROVED === $threeDAuthStatus && null !== $rawPaymentResponseData) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'eci'           => $raw3DAuthResponseData['Eci'],
            'cavv'          => $raw3DAuthResponseData['Cavv'],
            'auth_code'     => null,
            'order_id'      => $raw3DAuthResponseData['VerifyEnrollmentRequestId'],
            'status'        => $threeDAuthStatus,
            'status_detail' => null,
            'error_code'    => self::TX_DECLINED === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorCode'] : null,
            'error_message' => self::TX_DECLINED === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorMessage'] : null,
            'all'           => $rawPaymentResponseData,
            '3d_all'        => $raw3DAuthResponseData,
        ];

        if (empty($paymentResponseData)) {
            return array_merge($this->getDefaultPaymentResponse(), $threeDResponse);
        }

        return array_merge($threeDResponse, $paymentResponseData);
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
        $resultCode      = $this->getProcReturnCode($rawResponseData);
        if (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'order_id'         => $rawResponseData['TransactionId'] ?? null,
            'auth_code'        => (self::TX_DECLINED !== $status) ? $rawResponseData['AuthCode'] : null,
            'ref_ret_num'      => $rawResponseData['Rrn'] ?? null,
            'proc_return_code' => $resultCode,
            'trans_id'         => $rawResponseData['TransactionId'] ?? null,
            'error_code'       => (self::TX_DECLINED === $status) ? $rawResponseData['ResultDetail'] : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $rawResponseData['ResultDetail'] : null,
            'status'           => $status,
            'status_detail'    => $rawResponseData['ResultDetail'],
            'all'              => $rawResponseData,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function mapStatusResponse(array $rawResponseData): array
    {
        return $this->emptyStringsToNull($rawResponseData);
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$rawPaymentResponseData]);
        $rawPaymentResponseData = $this->emptyStringsToNull($rawPaymentResponseData);
        $commonResponse         = $this->getCommonPaymentResponse($rawPaymentResponseData);
        if (self::TX_APPROVED === $commonResponse['status']) {
            $commonResponse['trans_id']         = $rawPaymentResponseData['TransactionId'];
            $commonResponse['auth_code']        = $rawPaymentResponseData['AuthCode'];
            $commonResponse['ref_ret_num']      = $rawPaymentResponseData['Rrn'];
            $commonResponse['order_id']         = $rawPaymentResponseData['OrderId'];
            $commonResponse['transaction_type'] = $this->mapTxType($rawPaymentResponseData['TransactionType']);
            $commonResponse['eci']              = $rawPaymentResponseData['ECI'];
        }

        $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $commonResponse);

        return $commonResponse;
    }

    /**
     * @param array<string, string> $responseData
     *
     * @return array<string, string>
     */
    private function getCommonPaymentResponse(array $responseData): array
    {
        $status     = self::TX_DECLINED;
        $resultCode = $this->getProcReturnCode($responseData);
        if (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        }

        return [
            'trans_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => null,
            'order_id'         => null,
            'eci'              => null,
            'proc_return_code' => $resultCode,
            'status'           => $status,
            'status_detail'    => $responseData['ResultDetail'],
            'error_code'       => (self::TX_DECLINED === $status) ? $resultCode : null,
            'error_message'    => (self::TX_DECLINED === $status) ? $responseData['ResultDetail'] : null,
            'all'              => $responseData,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function mapHistoryResponse(array $rawResponseData): array
    {
        return $this->emptyStringsToNull($rawResponseData);
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
}
