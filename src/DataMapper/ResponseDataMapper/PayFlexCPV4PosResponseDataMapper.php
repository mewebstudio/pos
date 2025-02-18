<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

class PayFlexCPV4PosResponseDataMapper extends AbstractResponseDataMapper
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '0000';

    /**
     * Response Codes
     *
     * @var array<string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
    ];

    /**
     * {@inheritdoc}
     */
    public function map3DPaymentData(array $raw3DAuthResponseData, ?array $rawPaymentResponseData, string $txType, array $order): array
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
    public function map3DPayResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $raw3DAuthResponseData = $this->emptyStringsToNull($raw3DAuthResponseData);

        $paymentResponse = $this->getCommonPaymentResponse($raw3DAuthResponseData, $txType, PosInterface::MODEL_3D_PAY);
        $paymentResponse['md_status'] = null;
        $paymentResponse['md_error_message'] = null;
        $paymentResponse['transaction_security'] = null;

        $paymentResponse['transaction_id'] = $raw3DAuthResponseData['TransactionId'];
        $paymentResponse['masked_number']  = $raw3DAuthResponseData['MaskedPan'];

        if (self::TX_APPROVED === $paymentResponse['status']) {
            $paymentResponse['auth_code']         = $raw3DAuthResponseData['AuthCode'];
            $paymentResponse['ref_ret_num']       = $raw3DAuthResponseData['TransactionId'];
            $paymentResponse['order_id']          = $raw3DAuthResponseData['OrderID'];
            $paymentResponse['currency']          = $this->mapCurrency($raw3DAuthResponseData['AmountCode']);
            $paymentResponse['amount']            = $this->formatAmount($raw3DAuthResponseData['Amount']);
            $paymentResponse['transaction_type']  = $this->mapTxType($raw3DAuthResponseData['TransactionType']);
            $paymentResponse['installment_count'] = $this->mapInstallment($raw3DAuthResponseData['InstallmentCount']);
            $paymentResponse['transaction_time']  = new \DateTimeImmutable($raw3DAuthResponseData['HostDate']);
        }

        return $paymentResponse;
    }

    /**
     * {@inheritdoc}
     *
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
    public function map3DHostResponseData(array $raw3DAuthResponseData, string $txType, array $order): array
    {
        $response = $this->map3DPayResponseData($raw3DAuthResponseData, $txType, $order);

        $response['payment_model'] = PosInterface::MODEL_3D_HOST;

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function mapCancelResponse(array $rawResponseData): array
    {
        throw new NotImplementedException();
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
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
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
        return true;
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return null;
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
        return $response['Rc'] ?? $response['ResultCode'] ?? null;
    }


    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     * @phpstan-param PosInterface::MODEL_*       $paymentModel
     *
     * @param array<string, string> $responseData
     * @param string                $txType
     * @param string                $paymentModel
     *
     * @return array{order_id: string|null, transaction_id: string|null, auth_code: string|null,
     *     ref_ret_num: string|null, proc_return_code: string|null,
     *     status: string, status_detail: string|null, error_code: string|null,
     *     error_message: string|null, all: array<string, string|null>}
     */
    private function getCommonPaymentResponse(array $responseData, string $txType, string $paymentModel): array
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

        $response = $this->getDefaultPaymentResponse($txType, $paymentModel);

        $response['proc_return_code'] = $resultCode;
        $response['status']           = $status;
        $response['status_detail']    = null;
        $response['error_code']       = (self::TX_DECLINED === $status) ? $resultCode : null;
        $response['error_message']    = $errorMsg;
        $response['all']              = $responseData;

        return $response;
    }
}
