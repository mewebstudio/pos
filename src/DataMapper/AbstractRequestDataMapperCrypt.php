<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * extended by request data mappers that needs to hash data
 */
abstract class AbstractRequestDataMapperCrypt extends AbstractRequestDataMapper
{
    /** @var CryptInterface */
    protected $crypt;

    /**
     * @param EventDispatcherInterface                $eventDispatcher
     * @param CryptInterface                          $crypt
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, CryptInterface $crypt, array $currencyMappings = [])
    {
        parent::__construct($eventDispatcher, $crypt, $currencyMappings);
    }

    /**
     * @return CryptInterface
     */
    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
    }
}
