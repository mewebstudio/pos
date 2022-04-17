<?php

namespace Mews\Pos\Entity\Card;

use DateTimeImmutable;

/**
 * Class AbstractCreditCard
 */
class CreditCard extends AbstractCreditCard
{
    /**
     * 16 digit credit card number without spaces
     * @var string
     */
    protected $number;

    /**
     * @var DateTimeImmutable
     */
    protected $expDate;

    /**
     * @var string
     */
    protected $cvv;

    /**
     * @var string|null
     */
    protected $holderName;

    /**
     * visa, master, troy, amex, ...
     * @var string|null
     */
    protected $type;

    /**
     * AbstractCreditCard constructor.
     *
     * @param string            $number
     * @param DateTimeImmutable $expDate
     * @param string            $cvv
     * @param string|null       $cardHolderName
     * @param string|null       $cardType       visa, master
     */
    public function __construct(string $number, DateTimeImmutable $expDate, string $cvv, ?string $cardHolderName = null, ?string $cardType = null)
    {
        $this->expDate    = $expDate;
        parent::__construct($number, $expDate->format('y'), $expDate->format('m'), $cvv, $cardHolderName, $cardType);
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
