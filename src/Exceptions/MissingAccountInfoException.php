<?php

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
    public function __construct($message = 'Missing Account Information!', $code = 430, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
