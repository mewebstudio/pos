<?php

namespace Mews\Pos\Entity\Card;

/**
 * VakifBank credit card class for secure payments
 * Class CreditCardVakifBank
 */
class CreditCardVakifBank extends AbstractCreditCard
{
    private static $cardTypeToCodeMapping = [
        self::CARD_TYPE_VISA       => '100',
        self::CARD_TYPE_MASTERCARD => '200',
        self::CARD_TYPE_TROY       => '300',
        self::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * @inheritDoc
     */
    public function getExpirationDate(): string
    {
        return $this->getExpireYear().$this->getExpireMonth();
    }

    /**
     * yyyymm de formatinda tarih dondurur
     * @return string
     */
    public function getExpirationDateLong(): string
    {
        return $this->getExpireYearLong().$this->getExpireMonth();
    }

    /**
     * returns exp year in 4 digit format
     * @return string
     */
    public function getExpireYearLong(): string
    {
        return $this->expireYear->format('Y');
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
