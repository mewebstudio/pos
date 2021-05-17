<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class AbstractCreditCard
 */
abstract class AbstractCreditCard
{
    /**
     * @var string
     */
    protected $number;

    /**
     * @var \DateTime
     */
    protected $expireYear;

    /**
     * @var \DateTime
     */
    protected $expireMonth;

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
     * @param string      $number         credit card number with or without spaces
     * @param string      $expireYear     accepts year in 1, 2 and 4 digit format. accepted year formats '1' (2001), '02' (2002), '20' (2020), '2024' (2024)
     * @param string      $expireMonth    single digit or double digit month values are accepted
     * @param string      $cvv
     * @param string|null $cardHolderName
     * @param string|null $cardType       examples values: 'visa', 'master', '1', '2'
     */
    public function __construct(string $number, string $expireYear, string $expireMonth, string $cvv, ?string $cardHolderName = null, ?string $cardType = null)
    {
        $this->number = preg_replace('/\s+/', '', $number);

        $yearFormat = 4 === strlen($expireYear) ? 'Y' : 'y';
        $this->expireYear = \DateTime::createFromFormat($yearFormat, $expireYear);

        $this->expireMonth = \DateTime::createFromFormat('m', $expireMonth);
        $this->cvv = $cvv;
        $this->holderName = $cardHolderName;
        $this->type = $cardType;
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
     * @return string
     */
    public function getExpireYear(): string
    {
        return $this->expireYear->format('y');
    }

    /**
     * returns exp year in 2 digit format. i.e '01' '02' '12'
     * @return string
     */
    public function getExpireMonth(): string
    {
        return $this->expireMonth->format('m');
    }

    /**
     * returns card exp date month and year combined.
     * @return string
     */
    abstract public function getExpirationDate(): string;

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
    public function getType()
    {
        return $this->type;
    }
}
