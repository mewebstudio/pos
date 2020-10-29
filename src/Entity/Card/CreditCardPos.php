<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardEstPos
 */
class CreditCardPos extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().$this->getExpireYear();
    }
}