<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractResponseDataMapper implements ResponseDataMapperInterface
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '00';

    protected LoggerInterface $logger;

    /** @var array<string, PosInterface::CURRENCY_*> */
    protected array $currencyMappings;

    /** @var array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>> */
    protected array $txTypeMappings;

    /** @var array<string, PosInterface::MODEL_*> */
    protected array $secureTypeMappings;

    /**
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string>  $txTypeMappings
     * @param array<PosInterface::MODEL_*, string>    $secureTypeMappings
     * @param LoggerInterface                         $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, array $secureTypeMappings, LoggerInterface $logger)
    {
        $this->logger             = $logger;
        $this->currencyMappings   = \array_flip($currencyMappings);
        $this->txTypeMappings     = $txTypeMappings;
        $this->secureTypeMappings = \array_flip($secureTypeMappings);
    }

    /**
     * @return array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @param string|int $txType
     *
     * @return PosInterface::TX_*|null
     */
    public function mapTxType($txType): ?string
    {
        foreach ($this->txTypeMappings as $mappedTxType => $mapping) {
            if (\is_array($mapping) && \in_array($txType, $mapping, true)) {
                return $mappedTxType;
            }

            if ($mapping === $txType) {
                return $mappedTxType;
            }
        }

        return null;
    }

    /**
     * @param string|int $securityType
     *
     * @return PosInterface::MODEL_*|null
     */
    public function mapSecurityType($securityType): ?string
    {
        return $this->secureTypeMappings[$securityType] ?? null;
    }

    /**
     * "1000.01" => 1000.01
     * @param string $amount
     *
     * @return float
     */
    protected function formatAmount(string $amount): float
    {
        return (float) $amount;
    }

    /**
     * @param string $currency currency code that is accepted by bank
     *
     * @return PosInterface::CURRENCY_*|string
     */
    protected function mapCurrency(string $currency): string
    {
        return $this->currencyMappings[$currency] ?? $currency;
    }

    /**
     * @param string|null $installment
     *
     * @return int
     */
    protected function mapInstallment(?string $installment): int
    {
        return (int) $installment;
    }

    /**
     * if 2 arrays has common keys, then non-null value preferred,
     * if both arrays has the non-null values for the same key then value of $arr2 is preferred.
     *
     * @param array<string, mixed> $arr1
     * @param array<string, mixed> $arr2
     *
     * @return array<string, mixed>
     */
    protected function mergeArraysPreferNonNullValues(array $arr1, array $arr2): array
    {
        $resultArray     = \array_diff_key($arr1, $arr2) + \array_diff_key($arr2, $arr1);
        $commonArrayKeys = \array_keys(\array_intersect_key($arr1, $arr2));
        foreach ($commonArrayKeys as $key) {
            $resultArray[$key] = $arr2[$key] ?? $arr1[$key];
        }

        return $resultArray;
    }

    /**
     * Returns default payment response data
     * @phpstan-param PosInterface::TX_TYPE_PAY_* $txType
     * @phpstan-param PosInterface::MODEL_*|null  $paymentModel
     *
     * @param string      $txType
     * @param string|null $paymentModel
     *
     * @return array{order_id: null, transaction_id: null, auth_code: null, ref_ret_num: null, proc_return_code: null,
     *     status: string, status_detail: null, error_code: null, error_message: null, all: null}
     */
    protected function getDefaultPaymentResponse(string $txType, ?string $paymentModel): array
    {
        return [
            'order_id'          => null,
            'transaction_id'    => null,
            'transaction_time'  => null,
            'transaction_type'  => $txType,
            'installment_count' => null,
            'currency'          => null,
            'amount'            => null,
            'payment_model'     => $paymentModel,
            'auth_code'         => null,
            'ref_ret_num'       => null,
            'batch_num'         => null,
            'proc_return_code'  => null,
            'status'            => self::TX_DECLINED,
            'status_detail'     => null,
            'error_code'        => null,
            'error_message'     => null,
            'all'               => null,
        ];
    }

    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    protected function getDefaultStatusResponse(array $rawData): array
    {
        return [
            'order_id'          => null,
            'auth_code'         => null,
            'proc_return_code'  => null,
            'transaction_id'    => null,
            'transaction_time'  => null,
            'capture_time'      => null,
            'error_message'     => null,
            'ref_ret_num'       => null,
            'order_status'      => null,
            'transaction_type'  => null,
            'first_amount'      => null,
            'capture_amount'    => null,
            'status'            => self::TX_DECLINED,
            'error_code'        => null,
            'status_detail'     => null,
            'capture'           => null,
            'currency'          => null,
            'masked_number'     => null,
            'refund_amount'     => null,
            'installment_count' => null,
            'refund_time'       => null,
            'cancel_time'       => null,
            'all'               => $rawData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultOrderHistoryTxResponse(): array
    {
        return [
            'auth_code'        => null,
            'proc_return_code' => null,
            'transaction_id'   => null,
            'transaction_time' => null,
            'capture_time'     => null,
            'error_message'    => null,
            'ref_ret_num'      => null,
            'order_status'     => null,
            'transaction_type' => null,
            'first_amount'     => null,
            'capture_amount'   => null,
            'status'           => self::TX_DECLINED,
            'error_code'       => null,
            'status_detail'    => null,
            'capture'          => null,
            'currency'         => null,
            'masked_number'    => null,
        ];
    }

    /**
     * bankadan gelen response'da bos string degerler var.
     * bu metod ile bos string'leri null deger olarak degistiriyoruz
     *
     * @param mixed $data
     *
     * @return mixed
     */
    protected function emptyStringsToNull($data)
    {
        $result = null;
        if (\is_string($data)) {
            $data   = \trim($data);
            $result = '' === $data ? null : $data;
        } elseif (\is_numeric($data)) {
            $result = $data;
        } elseif (\is_array($data) || \is_object($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::emptyStringsToNull($value);
            }
        }

        return $result;
    }
}
