<?php

namespace Mews\Pos\DataMapper\ResponseDataMapper;

use Mews\Pos\Gateways\AbstractGateway;
use Psr\Log\LoggerInterface;

abstract class AbstractResponseDataMapper
{
    public const TX_APPROVED = 'approved';
    public const TX_DECLINED = 'declined';
    public const PROCEDURE_SUCCESS_CODE = '00';

    /** @var LoggerInterface */
    protected $logger;

    /** @var array<string, string> */
    private $currencyMappings;

    /** @var array<string, AbstractGateway::TX_*> */
    protected $txTypeMappings;

    /**
     * @param array<string, string>                $currencyMappings
     * @param array<AbstractGateway::TX_*, string> $txTypeMappings
     * @param LoggerInterface                      $logger
     */
    public function __construct(array $currencyMappings, array $txTypeMappings, LoggerInterface $logger)
    {
        $this->logger           = $logger;
        $this->currencyMappings = array_flip($currencyMappings);
        $this->txTypeMappings   = array_flip($txTypeMappings);
    }

    /**
     * @return array<string, AbstractGateway::TX_*>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @param string $txType
     *
     * @return string
     */
    public function mapTxType(string $txType): string
    {
        return $this->txTypeMappings[$txType] ?? $txType;
    }

    /**
     * "1000.01" => 1000.01
     * @param string $amount
     *
     * @return float
     */
    public static function amountFormat(string $amount): float
    {
        return (float) $amount;
    }

    /**
     * @param string $mdStatus
     *
     * @return string
     */
    protected abstract function mapResponseTransactionSecurity(string $mdStatus): string;

    /**
     * @param string $currency TRY, USD
     *
     * @return string currency code that is accepted by bank
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
     * @return array<string, string|null>
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
