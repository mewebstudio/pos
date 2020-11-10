<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardEstPos
 */
class CreditCardEstPos extends AbstractCreditCard
{
    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().'/'.$this->getExpireYear();
    }

    /**
     * returns null, '1' or '2'
     * @return string|null
     */
    public function getCardCode()
    {
        if (null === $this->type || '1' === $this->type || '2' === $this->type) {
            return $this->type;
        }

        if ('visa' === $this->type) {
            return '1';
        }
        if ('master' === $this->type) {
            return '2';
        }

        return null;
    }
}