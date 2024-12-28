<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exceptions;

use Exception;
use Throwable;

/**
 * Class BankClassNullException
 */
class BankClassNullException extends Exception
{
    /**
     * BankClassNullException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Class must be specified!', int $code = 331, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
