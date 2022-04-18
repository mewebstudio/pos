<?php

namespace Mews\Pos\Entity\Card;

/**
 * @deprecated 0.6.0 No longer used by internal code and will be removed in the next major release
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
