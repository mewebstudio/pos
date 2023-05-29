<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;

class PayFlexCPV4PosResponseDataMapper extends AbstractResponseDataMapper implements PaymentResponseMapperInterface, NonPaymentResponseMapperInterface
{
    /** @var string */
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
     * TODO implement
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param array{ErrorCode: string}|array{
     *     Rc: string,
     *     AuthCode: string,
     *     TransactionId: string,
     *     PaymentToken: string,
     *     MaskedPan: string}|array{
     *     Rc: string,
     *     Message: string,
     *     TransactionId: string,
     *     PaymentToken: string} $raw3DAuthResponseData
     * {@inheritdoc}
     */
    public function map3DPayResponseData(array $raw3DAuthResponseData): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $paymentResponse = $this->getCommonPaymentResponse($raw3DAuthResponseData);
        $paymentResponse['md_status'] = null;
        $paymentResponse['md_error_message'] = null;
        $paymentResponse['transaction_security'] = null;

        $paymentResponse['trans_id']      = $raw3DAuthResponseData['TransactionId'];
        $paymentResponse['masked_number'] = $raw3DAuthResponseData['MaskedPan'];

        if (self::TX_APPROVED === $paymentResponse['status']) {
            $paymentResponse['auth_code']   = $raw3DAuthResponseData['AuthCode'];
            $paymentResponse['ref_ret_num'] = $raw3DAuthResponseData['TransactionId'];
            $paymentResponse['order_id'] = $raw3DAuthResponseData['OrderID'];
        }

        return $paymentResponse;
    }

    /**
     * {@inheritdoc}
     * @param array{ErrorCode: string}|array{
     *     Rc: string,
     *     AuthCode: string,
     *     TransactionId: string,
     *     PaymentToken: string,
     *     MaskedPan: string}|array{
     *     Rc: string,
     *     Message: string,
     *     TransactionId: string,
     *     PaymentToken: string} $raw3DAuthResponseData
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
    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);

        $response = $this->getCommonNonSecureResponse($rawResponseData);

        if (self::TX_APPROVED === $response['status']) {
            $response['order_id'] = $rawResponseData['TransactionId'];
            $response['ref_ret_num'] = $rawResponseData['Rrn'];
            $response['auth_code'] = $rawResponseData['AuthCode'];
            $response['trans_id'] = $rawResponseData['TransactionId'];
        }

        return $response;
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
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', $rawPaymentResponseData);
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
     * @return array{order_id: string|null, trans_id: string|null, auth_code: string|null,
     *     ref_ret_num: string|null, proc_return_code: string|null,
     *     status: string, status_detail: string|null, error_code: string|null,
     *     error_message: string|null, all: array<string, string|null>}
     */
    private function getCommonPaymentResponse(array $responseData): array
    {
        $status     = self::TX_DECLINED;
        $resultCode = $this->getProcReturnCode($responseData);

        $errorCode = $responseData['ErrorCode'] ?? null;
        $errorMsg  = null;
        if (null !== $errorCode) {
            $resultCode = $errorCode;
            $errorMsg   = $responseData['Message'] ?? $responseData['ResponseMessage'];
        } elseif (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        } else {
            $errorMsg = $responseData['Message'];
        }

        $response = $this->getDefaultPaymentResponse();

        $response['proc_return_code'] = $resultCode;
        $response['status'] = $status;
        $response['status_detail'] = null;
        $response['error_code'] = (self::TX_DECLINED === $status) ? $resultCode : null;
        $response['error_message'] = $errorMsg;
        $response['all'] = $responseData;

        return $response;
    }

    /**
     * @param array<string, string> $responseData
     *
     * @return array{order_id: string|null, trans_id: string|null, auth_code: string|null,
     *     ref_ret_num: string|null, proc_return_code: string|null,
     *     status: string, status_detail: string|null, error_code: string|null,
     *     error_message: string|null, all: array<string, string|null>}
     */
    private function getCommonNonSecureResponse(array $responseData): array
    {
        $status     = self::TX_DECLINED;
        $resultCode = $this->getProcReturnCode($responseData);

        $errorCode       = $responseData['ErrorCode'] ?? null;
        $statusDetail    = null;
        if (null !== $errorCode) {
            $resultCode   = $errorCode;
            $statusDetail = $responseData['ResponseMessage'];
        } elseif (self::PROCEDURE_SUCCESS_CODE === $resultCode) {
            $status = self::TX_APPROVED;
        } else {
            $statusDetail = $responseData['ResultDetail'];
        }

        $response = $this->getDefaultPaymentResponse();

        $response['proc_return_code'] = $resultCode;
        $response['status'] = $status;
        $response['status_detail'] = $statusDetail;
        $response['error_code'] = (self::TX_DECLINED === $status) ? $resultCode : null;
        $response['error_message'] = $statusDetail;
        $response['all'] = $responseData;

        return $response;
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
        if (isset($response['Rc'])) {
            return $response['Rc'];
        }

        return $response['ResultCode'] ?? null;
    }
}
