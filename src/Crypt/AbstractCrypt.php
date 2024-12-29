<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Psr\Log\LoggerInterface;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

abstract class AbstractCrypt implements CryptInterface
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha1';

    /** @var string */
    protected const HASH_SEPARATOR = '';

    protected LoggerInterface $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function generateRandomString(int $length = 24): string
    {
        $characters = '0123456789ABCDEF';
        $charactersLength = \strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[\random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * @inheritDoc
     */
    public function hashFromParams(string $storeKey, array $data, string $hashParamsKey, string $paramSeparator = ':'): string
    {
        $hashParams = $this->recursiveFind($data, $hashParamsKey);
        if ('' === $hashParams) {
            return '';
        }

        /**
         * @var non-empty-string $hashParams ex: "MerchantNo:TerminalNo:ReferenceCode:OrderId"
         */
        $hashParamsArr = \explode($paramSeparator, $hashParams);

        $hashVal = $this->buildHashString($data, $hashParamsArr, '', $storeKey);

        return $this->hashString($hashVal, $storeKey);
    }

    /**
     * @inheritDoc
     */
    public function hashString(string $str, ?string $encryptionKey = null): string
    {
        return \base64_encode(\hash(static::HASH_ALGORITHM, $str, true));
    }

    /**
     * @param string $hashKey
     * @param string $hashString
     *
     * @return string
     */
    protected function concatenateHashKey(string $hashKey, string $hashString): string
    {
        return $hashString.$hashKey;
    }

    /**
     * @param array<string, mixed> $data       data from which the hash string will be built
     * @param string[]             $paramNames parameter names that will be used in hash calculation
     * @param string               $separator  separator between the parameter values
     * @param string|null          $storeKey   secret key of the API, will be attached to the hash string if provided
     *
     * @return string string data to be hashed
     */
    protected function buildHashString(array $data, array $paramNames, string $separator = '', ?string $storeKey = null): string
    {
        $paramsVal = \implode($separator, $this->buildHashData($data, $paramNames));

        if (null !== $storeKey) {
            $paramsVal = $this->concatenateHashKey($storeKey, $paramsVal);
        }

        return $paramsVal;
    }

    /**
     * @param array<string, mixed> $data       data from which the hash string will be built
     * @param string[]             $paramNames parameter names that will be used in hash calculation
     *
     * @return string[]
     */
    protected function buildHashData(array $data, array $paramNames): array
    {
        $paramsVal = [];
        foreach ($paramNames as $paramKey) {
            $paramsVal[] = $this->recursiveFind($data, $paramKey);
        }

        return $paramsVal;
    }

    /**
     * @param array<string, mixed> $haystack (multidimensional) array
     * @param string               $needle   key name that will be searched in the (multidimensional) array
     *
     * @return string the value of the $needle in the (multidimensional) array
     */
    private function recursiveFind(array $haystack, string $needle): string
    {
        $iterator  = new RecursiveArrayIterator($haystack);
        $recursive = new RecursiveIteratorIterator(
            $iterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($recursive as $key => $value) {
            if ($key === $needle) {
                return (string) $value;
            }
        }

        return '';
    }
}
