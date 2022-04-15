<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardEstPos
 */
class CreditCardInterPos extends AbstractCreditCard
{
    private $cardTypeToCodeMapping = [
        self::CARD_TYPE_VISA       => '0',
        self::CARD_TYPE_MASTERCARD => '1',
        self::CARD_TYPE_AMEX       => '3',
    ];

    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireMonth().$this->getExpireYear();
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
