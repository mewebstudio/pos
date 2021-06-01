<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardEstPos
 */
class CreditCardEstPos extends AbstractCreditCard
{
    private $cardTypeToCodeMapping = [
        'visa'   => '1',
        'master' => '2',
    ];

    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().'/'.$this->getExpireYear();
    }

    /**
     * @return string
     */
    public function getCardCode(): string
    {
        if (!isset($this->cardTypeToCodeMapping[$this->type])) {
            return $this->type;
        }

        return $this->cardTypeToCodeMapping[$this->type];
    }

    /**
     * @return string[]
     */
    public function getCardTypeToCodeMapping(): array
    {
        return $this->cardTypeToCodeMapping;
    }
}
