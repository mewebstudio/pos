<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper;

use Mews\Pos\Crypt\CryptInterface;

/**
 * extended by request data mappers that needs to hash data
 */
abstract class AbstractRequestDataMapperCrypt extends AbstractRequestDataMapper
{
    /** @var CryptInterface */
    protected $crypt;

    /**
     * @param array<string, string> $currencyMappings
     */
    public function __construct(CryptInterface $crypt, array $currencyMappings = [])
    {
        parent::__construct($crypt, $currencyMappings);
    }

    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
    }
}
