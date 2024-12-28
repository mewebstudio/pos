<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Card;

use DateTimeImmutable;

/**
 * Class AbstractCreditCard
 */
abstract class AbstractCreditCard implements CreditCardInterface
{
    /**
     * 16 digit credit card number without spaces
     */
    protected string $number;

    protected DateTimeImmutable $expDate;

    protected string $cvv;

    protected ?string $holderName;

    /**
     * @phpstan-var CreditCardInterface::CARD_TYPE_*
     */
    protected ?string $type;

    /**
     * @phpstan-param CreditCardInterface::CARD_TYPE_*|null $cardType
     *
     * @param string            $number   credit card number with or without spaces
     * @param DateTimeImmutable $expDate
     * @param string            $cvv
     * @param string|null       $cardHolderName
     * @param string|null       $cardType examples values: 'visa', 'master', '1', '2'
     *
     * @throws \LogicException
     */
    public function __construct(string $number, DateTimeImmutable $expDate, string $cvv, ?string $cardHolderName = null, ?string $cardType = null)
    {
        $number = \preg_replace('/\s+/', '', $number);
        if (null === $number) {
            throw new \LogicException('Kredit numarası formatlanamadı!');
        }

        $this->number     = $number;
        $this->expDate    = $expDate;
        $this->cvv        = $cvv;
        $this->holderName = $cardHolderName;
        $this->type       = $cardType;
    }

    /**
     * @inheritDoc
     */
    public function getNumber(): string
    {
        return $this->number;
    }

    /**
     * @inheritDoc
     */
    public function getExpireYear(string $format = 'y'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @inheritDoc
     */
    public function getExpireMonth(string $format = 'm'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @inheritDoc
     */
    public function getExpirationDate(string $format = 'ym'): string
    {
        return $this->expDate->format($format);
    }

    /**
     * @inheritDoc
     */
    public function getCvv(): string
    {
        return $this->cvv;
    }

    /**
     * @inheritDoc
     */
    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    /**
     * @inheritDoc
     */
    public function setHolderName(?string $name): CreditCardInterface
    {
        $this->holderName = $name;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
