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
     * @return \DateTimeImmutable
     */
    public function getExpirationDate(): \DateTimeImmutable;

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
