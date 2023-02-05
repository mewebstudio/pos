<?php

namespace Mews\Pos\Entity\Card;

use DateTimeImmutable;

/**
 * Class AbstractCreditCard
 */
abstract class AbstractCreditCard
{
    public const CARD_TYPE_VISA = 'visa';
    public const CARD_TYPE_MASTERCARD = 'master';
    public const CARD_TYPE_AMEX = 'amex';
    public const CARD_TYPE_TROY = 'troy';

    /**
     * 16 digit credit card number without spaces
     * @var string
     */
    protected $number;

    /** @var DateTimeImmutable */
    protected $expDate;

    /** @var string */
    protected $cvv;

    /** @var string|null */
    protected $holderName;

    /**
     * visa, master, troy, amex, ...
     * @var self::CARD_TYPE_*|null
     */
    protected $type;

    /**
     * AbstractCreditCard constructor.
     *
     * @param string                 $number   credit card number with or without spaces
     * @param self::CARD_TYPE_*|null $cardType
     */
    public function __construct(string $number, DateTimeImmutable $expDate, string $cvv, ?string $cardHolderName = null, ?string $cardType = null)
    {
        $this->number     = preg_replace('/\s+/', '', $number);
        $this->expDate    = $expDate;
        $this->cvv        = $cvv;
        $this->holderName = $cardHolderName;
        $this->type       = $cardType;
    }

    /**
     * returns card number without white spaces
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @return string year by default in 2 digit format.
     */
    public function getExpireYear(string $format = 'y'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @return string month number, by default in 2 digit format. i.e '01' '02' '12'
     */
    public function getExpireMonth(string $format = 'm'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @return string card exp date month and year combined.
     */
    public function getExpirationDate(string $format = 'ym'): string
    {
        return $this->expDate->format($format);
    }

    public function getCvv(): string
    {
        return $this->cvv;
    }

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function setHolderName(?string $name)
    {
        $this->holderName = $name;
    }

    /**
     * @return self::CARD_TYPE_*|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
