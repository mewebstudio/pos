<?php

namespace Mews\Pos\Entity\Card;

/**
 * Class CreditCardVakifBank
 */
class CreditCardVakifBank extends AbstractCreditCard
{
    private static $cardTypeToCodeMapping = [
        'visa'   => '100',
        'master' => '200',
        'troy'   => '300',
        'amex'   => '400',
    ];

    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireYear().$this->getExpireMonth();
    }

    /**
     * @return string
     */
    public function getCardCode(): string
    {
        if (!isset(self::$cardTypeToCodeMapping[$this->type])) {
            return $this->type;
        }

        return self::$cardTypeToCodeMapping[$this->type];
    }

    /**
     * @return string[]
     */
    public static function getCardTypeToCodeMapping(): array
    {
        return self::$cardTypeToCodeMapping;
    }
}
