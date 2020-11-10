<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardGarantiPos
 */
class CreditCardGarantiPos extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().$this->getExpireYear();
    }
}