<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardPosNet
 */
class CreditCardPosNet extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireYear().$this->getExpireMonth();
    }
}