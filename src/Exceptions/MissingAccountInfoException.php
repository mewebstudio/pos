<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Exceptions;

use Exception;
use Throwable;

/**
 * Class MissingAccountInfoException
 */
class MissingAccountInfoException extends Exception
{
    /**
     * BankNotFoundException constructor.
     *
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = 'Missing Account Information!', int $code = 430, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
