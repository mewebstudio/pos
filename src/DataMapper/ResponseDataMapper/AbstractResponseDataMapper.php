<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\PosInterface;
use Psr\Log\LoggerInterface;
use function array_diff_key;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function is_numeric;
use function is_object;
use function is_string;
use function trim;

abstract class AbstractResponseDataMapper implements ResponseDataMapperInterface
{
    /** @var string */
    public const PROCEDURE_SUCCESS_CODE = '00';

    protected LoggerInterface $logger;

    /** @var array<string, PosInterface::CURRENCY_*> */
    private array $currencyMappings;

    /** @var array<string, PosInterface::TX_TYPE_*> */
    protected array $txTypeMappings;

    /**
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     * @param array<PosInterface::TX_TYPE_*, string>       $txTypeMappings
     * @param LoggerInterface                         $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, LoggerInterface $logger)
    {
        $this->logger           = $logger;
        $this->currencyMappings = array_flip($currencyMappings);
        $this->txTypeMappings   = array_flip($txTypeMappings);
    }

    /**
     * @return array<string, PosInterface::TX_TYPE_*>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @param string|int $txType
     *
     * @return string|null
     */
    public function mapTxType($txType): ?string
    {
        return $this->txTypeMappings[$txType] ?? null;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    abstract protected function mapResponseTransactionSecurity(string $mdStatus): string;

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
        $resultArray     = array_diff_key($arr1, $arr2) + array_diff_key($arr2, $arr1);
        $commonArrayKeys = array_keys(array_intersect_key($arr1, $arr2));
        foreach ($commonArrayKeys as $key) {
            $resultArray[$key] = $arr2[$key] ?: $arr1[$key];
        }

        return $resultArray;
    }

    /**
     * Returns default payment response data
     *
     * @return array{order_id: null, trans_id: null, auth_code: null, ref_ret_num: null, proc_return_code: null,
     *     status: string, status_detail: null, error_code: null, error_message: null, all: null}
     */
    protected function getDefaultPaymentResponse(): array
    {
        return [
            'order_id'         => null,
            'trans_id'         => null,
            'auth_code'        => null,
            'ref_ret_num'      => null,
            'proc_return_code' => null,
            'status'           => self::TX_DECLINED,
            'status_detail'    => null,
            'error_code'       => null,
            'error_message'    => null,
            'all'              => null,
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
        if (is_string($data)) {
            $data   = trim($data);
            $result = '' === $data ? null : $data;
        } elseif (is_numeric($data)) {
            $result = $data;
        } elseif (is_array($data) || is_object($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = self::emptyStringsToNull($value);
            }
        }

        return $result;
    }
}
