<?php

namespace Mews\Pos\Exceptions;

use Exception;
use Throwable;

/**
 * Class BankClassNullException
 * @package Mews\Pos\Exceptions
 */
class BankClassNullException extends Exception
{
    /**
     * BankClassNullException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = 'Class must be specified!', $code = 331, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
