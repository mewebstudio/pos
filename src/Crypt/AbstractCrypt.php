<?php

namespace Mews\Pos\Crypt;

use Psr\Log\LoggerInterface;

abstract class AbstractCrypt implements CryptInterface
{
    protected const HASH_ALGORITHM = 'sha1';
    protected const HASH_SEPARATOR = '';

    /** @var LoggerInterface */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function hashString(string $str): string
    {
        return base64_encode(hash(static::HASH_ALGORITHM, $str, true));
    }
}
