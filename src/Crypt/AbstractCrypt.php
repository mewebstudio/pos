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

    /** @var LoggerInterface */
    protected $logger;

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
    public function hashFromParams(string $storeKey, array $data, string $hashParamsKey, string $paramSeparator = ':'): string
    {
        $hashParams = $this->recursiveFind($data, $hashParamsKey);
        if ('' === $hashParams) {
            return '';
        }

        /**
         * @var non-empty-string $hashParams ex: "MerchantNo:TerminalNo:ReferenceCode:OrderId"
         */
        $hashParamsArr = explode($paramSeparator, $hashParams);

        $paramsVal = '';
        foreach ($hashParamsArr as $paramKey) {
            $paramsVal .= $this->recursiveFind($data, $paramKey);
        }

        $hashVal = $paramsVal.$storeKey;

        return $this->hashString($hashVal);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function hashString(string $str): string
    {
        return base64_encode(hash(static::HASH_ALGORITHM, $str, true));
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
