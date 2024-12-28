<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exceptions;

use Exception;
use Throwable;

/**
 * Class UnsupportedTransactionTypeException
 */
class UnsupportedTransactionTypeException extends Exception
{
    /**
     * UnsupportedTransactionTypeException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Unsupported transaction type!', int $code = 332, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
