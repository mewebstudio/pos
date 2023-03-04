<?php

namespace Mews\Pos\Exceptions;

use DomainException;
use Throwable;

/**
 * thrown if card type is not provided
 */
class CardTypeRequiredException extends DomainException
{
    /**
     * @var string
     */
    private $gatewayName;

    /**
     * BankNotFoundException constructor.
     *
     * @param string         $gatewayName
     * @param string         $message
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $gatewayName, string $message = 'Card type is required for this gateway!', int $code = 73, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->gatewayName = $gatewayName;
    }

    /**
     * @return string
     */
    public function getGatewayName(): string
    {
        return $this->gatewayName;
    }
}
