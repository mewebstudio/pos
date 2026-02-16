<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;

class KuveytSoapApiPosResponseDataMapper extends AbstractResponseDataMapper
{
    /**
     * Response Codes
     *
     * @var array<int|string, string>
     */
    protected array $codes = [
        self::PROCEDURE_SUCCESS_CODE => self::TX_APPROVED,
        'ApiUserNotDefined'          => 'invalid_transaction',
        'EmptyMDException'           => 'invalid_transaction',
        'HashDataError'              => 'invalid_transaction',
    ];

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytSoapApiPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function mapPaymentResponse(array $rawPaymentResponseData, string $txType, array $order): array
    {
        throw new NotImplementedException();
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
    public function mapStatusResponse(array $rawResponseData): array
    {
        $txType          = PosInterface::TX_TYPE_STATUS;
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;
        $data            = $rawResponseData['GetMerchantOrderDetailResponse']['GetMerchantOrderDetailResult']['Value'];

        $defaultResponse = $this->getDefaultStatusResponse($rawResponseData);

        if (!isset($data['OrderContract'])) {
            if (isset($rawResponseData['GetMerchantOrderDetailResponse']['GetMerchantOrderDetailResult']['Results']['Result'])) {
                $rawResult                        = $rawResponseData['GetMerchantOrderDetailResponse']['GetMerchantOrderDetailResult']['Results']['Result'];
                $defaultResponse['error_code']    = $rawResult['ErrorCode'];
                $defaultResponse['error_message'] = $rawResult['ErrorMessage'];
            }

            return $defaultResponse;
        }

        $orderContract  = $rawResponseData['GetMerchantOrderDetailResponse']['GetMerchantOrderDetailResult']['Value']['OrderContract'];
        $procReturnCode = $this->getProcReturnCode($orderContract);

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $defaultResponse['status']           = $status;
        $defaultResponse['proc_return_code'] = $procReturnCode;

        if (self::TX_APPROVED === $status) {
            $defaultResponse['order_status']    = $this->valueMapper->mapOrderStatus($orderContract['LastOrderStatus']);
            $defaultResponse['order_id']        = $orderContract['MerchantOrderId'];
            $defaultResponse['remote_order_id'] = (string) $orderContract['OrderId'];

            $defaultResponse['auth_code']         = $orderContract['ProvNumber'];
            $defaultResponse['ref_ret_num']       = $orderContract['RRN'];
            $defaultResponse['transaction_id']    = $orderContract['Stan'];
            $defaultResponse['currency']          = $this->valueMapper->mapCurrency($orderContract['FEC'], $txType);
            $defaultResponse['first_amount']      = null === $orderContract['FirstAmount'] ? null : $this->valueFormatter->formatAmount($orderContract['FirstAmount'], $txType);
            $defaultResponse['masked_number']     = $orderContract['CardNumber'];
            $defaultResponse['transaction_time']  = $this->valueFormatter->formatDateTime($orderContract['OrderDate'], $txType);
            $defaultResponse['installment_count'] = $this->valueFormatter->formatInstallment($orderContract['InstallmentCount'], $txType);
            if (PosInterface::PAYMENT_STATUS_PAYMENT_COMPLETED === $defaultResponse['order_status']) {
                $defaultResponse['capture_amount'] = $defaultResponse['first_amount'];
                $defaultResponse['capture']        = $defaultResponse['first_amount'] > 0;
                if ($defaultResponse['capture']) {
                    $defaultResponse['capture_time'] = $this->valueFormatter->formatDateTime($orderContract['UpdateSystemDate'], $txType);
                }
            } elseif (PosInterface::PAYMENT_STATUS_CANCELED === $defaultResponse['order_status']) {
                $defaultResponse['cancel_time'] = $this->valueFormatter->formatDateTime($orderContract['UpdateSystemDate'], $txType);
            } elseif (PosInterface::PAYMENT_STATUS_FULLY_REFUNDED === $defaultResponse['order_status']) {
                $defaultResponse['refund_time'] = $this->valueFormatter->formatDateTime($orderContract['UpdateSystemDate'], $txType);
            }
        }

        return $defaultResponse;
    }

    /**
     * @inheritDoc
     */
    public function mapRefundResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;

        $result = [
            'order_id'         => null,
            'auth_code'        => null,
            'proc_return_code' => null,
            'transaction_id'   => null,
            'currency'         => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => null,
            'all'              => $rawResponseData,
        ];

        $drawbackResult = $rawResponseData['PartialDrawbackResponse']['PartialDrawbackResult']
            ?? $rawResponseData['DrawBackResponse']['DrawBackResult'];
        $value          = $drawbackResult['Value'];

        $procReturnCode = $this->getProcReturnCode($value);

        if (null === $procReturnCode) {
            return $result;
        }

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $responseResults = $drawbackResult['Results'];
        if (self::TX_APPROVED !== $status && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult             = $responseResults['Result'][0] ?? $responseResults['Result'];
            $result['proc_return_code'] = $procReturnCode;
            $result['error_code']       = $responseResult['ErrorCode'] ?? $procReturnCode;
            $result['error_message']    = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['transaction_id']   = $value['Stan'];
        $result['proc_return_code'] = $procReturnCode;
        $result['order_id']         = $value['MerchantOrderId'];
        $result['remote_order_id']  = (string) $value['OrderId'];
        $result['status']           = $status;

        if (self::TX_APPROVED === $status) {
            $result['currency']  = $this->valueMapper->mapCurrency($value['CurrencyCode'], PosInterface::TX_TYPE_REFUND);
            $result['auth_code'] = $value['ProvisionNumber'];
        } else {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $value['ResponseMessage'];
        }

        return $result;
    }

    public function mapCancelResponse(array $rawResponseData): array
    {
        $rawResponseData = $this->emptyStringsToNull($rawResponseData);
        $status          = self::TX_DECLINED;

        $result = [
            'order_id'         => null,
            'auth_code'        => null,
            'proc_return_code' => null,
            'transaction_id'   => null,
            'currency'         => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'status'           => $status,
            'error_code'       => null,
            'status_detail'    => null,
            'all'              => $rawResponseData,
        ];

        $value          = $rawResponseData['SaleReversalResponse']['SaleReversalResult']['Value'];
        $procReturnCode = $this->getProcReturnCode($value);

        if (null === $procReturnCode) {
            return $result;
        }

        if (self::PROCEDURE_SUCCESS_CODE === $procReturnCode) {
            $status = self::TX_APPROVED;
        }

        $responseResults = $rawResponseData['SaleReversalResponse']['SaleReversalResult']['Results'];
        if (self::TX_APPROVED !== $status && isset($responseResults['Result']) && [] !== $responseResults['Result']) {
            $responseResult             = $responseResults['Result'][0] ?? $responseResults['Result'];
            $result['proc_return_code'] = $procReturnCode;
            $result['error_code']       = $responseResult['ErrorCode'] ?? $procReturnCode;
            $result['error_message']    = $responseResult['ErrorMessage'];

            return $result;
        }

        $result['ref_ret_num']      = $value['RRN'];
        $result['transaction_id']   = $value['Stan'];
        $result['proc_return_code'] = $procReturnCode;
        $result['order_id']         = $value['MerchantOrderId'];
        $result['remote_order_id']  = (string) $value['OrderId'];
        $result['status']           = $status;
        $result['currency']         = $this->valueMapper->mapCurrency($value['CurrencyCode'], PosInterface::TX_TYPE_CANCEL);

        if (self::TX_APPROVED === $status) {
            $result['auth_code'] = $value['ProvisionNumber'];
        } else {
            $result['error_code']    = $procReturnCode;
            $result['error_message'] = $value['ResponseMessage'];
        }

        return $result;
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
        return self::PROCEDURE_SUCCESS_CODE === $mdStatus;
    }

    /**
     * @inheritDoc
     */
    public function extractMdStatus(array $raw3DAuthResponseData): ?string
    {
        return $this->getProcReturnCode($raw3DAuthResponseData);
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
        return $response['ResponseCode'] ?? null;
    }
}
