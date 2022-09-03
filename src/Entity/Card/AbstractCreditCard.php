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
     * @var string|null
     */
    protected $type;

    /**
     * AbstractCreditCard constructor.
     *
     * @param string            $number   credit card number with or without spaces
     * @param DateTimeImmutable $expDate
     * @param string            $cvv
     * @param string|null       $cardHolderName
     * @param string|null       $cardType examples values: 'visa', 'master', '1', '2'
     *
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
     * @return string
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * returns exp year in 2 digit format
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpireYear(string $format = 'y'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * returns exp year in 2 digit format. i.e '01' '02' '12'
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpireMonth(string $format = 'm'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * returns card exp date month and year combined.
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpirationDate(string $format = 'ym'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @return string
     */
    public function getCvv(): string
    {
        return $this->cvv;
    }

    /**
     * @return string|null
     */
    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    /**
     * @param string|null $name
     */
    public function setHolderName(?string $name)
    {
        $this->holderName = $name;
    }

    /**
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
