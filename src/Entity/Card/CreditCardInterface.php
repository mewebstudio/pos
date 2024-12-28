<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Card;

/**
 * Interface CreditCardInterface
 */
interface CreditCardInterface
{
    /** @var string */
    public const CARD_TYPE_VISA = 'visa';

    /** @var string */
    public const CARD_TYPE_MASTERCARD = 'master';

    /** @var string */
    public const CARD_TYPE_AMEX = 'amex';

    /** @var string */
    public const CARD_TYPE_TROY = 'troy';

    /**
     * returns card number without white spaces
     * @return string
     */
    public function getNumber(): string;

    /**
     * returns exp year in 2 digit format
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpireYear(string $format = 'y'): string;

    /**
     * returns exp year in 2 digit format. i.e '01' '02' '12'
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpireMonth(string $format = 'm'): string;

    /**
     * returns card exp date month and year combined.
     *
     * @param string $format
     *
     * @return string
     */
    public function getExpirationDate(string $format = 'ym'): string;

    /**
     * @return string
     */
    public function getCvv(): string;

    /**
     * @return string|null
     */
    public function getHolderName(): ?string;

    /**
     * @param string|null $name
     *
     * @return $this
     */
    public function setHolderName(?string $name): self;

    /**
     * @return CreditCardInterface::CARD_TYPE_*|null
     */
    public function getType(): ?string;
}
