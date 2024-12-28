<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exceptions;

use Exception;
use Throwable;

/**
 * Class UnsupportedPaymentModelException
 */
class UnsupportedPaymentModelException extends Exception
{
    /**
     * UnsupportedPaymentModelException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Unsupported payment model!', int $code = 333, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
