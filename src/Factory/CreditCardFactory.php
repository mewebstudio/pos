<?php

namespace Mews\Pos\Factory;

use DateTimeImmutable;
use DomainException;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Exceptions\CardTypeNotSupportedException;
use Mews\Pos\Exceptions\CardTypeRequiredException;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\PosInterface;

/**
 * CreditCardFactory
 */
class CreditCardFactory
{
    /**
     * AbstractCreditCard constructor.
     *
     * @param PosInterface|AbstractGateway         $pos
     * @param string                               $number      credit card number with or without spaces
     * @param string                               $expireYear  accepts year in 1, 2 and 4 digit format. accepted year
     *                                                          formats '1' (2001), '02'
     *                                                          (2002), '20' (2020), '2024' (2024)
     * @param string                               $expireMonth single digit or double digit month values are accepted
     * @param AbstractCreditCard::CARD_TYPE_*|null $cardType    bankaya gore zorunlu
     */
    public static function create(
        PosInterface $pos,
        string $number,
        string $expireYear,
        string $expireMonth,
        string $cvv,
        ?string $cardHolderName = null,
        ?string $cardType = null
    ): AbstractCreditCard {

        $number = preg_replace('/\s+/', '', $number);
        $expireYear =  str_pad($expireYear, 2, '0', STR_PAD_LEFT);
        $expireYear =  str_pad($expireYear, 4, '20', STR_PAD_LEFT);
        $expireMonth = str_pad($expireMonth, 2, '0', STR_PAD_LEFT);
        $expDate  = DateTimeImmutable::createFromFormat('Ymd', $expireYear.$expireMonth.'01');

        if (! $expDate instanceof DateTimeImmutable) {
            throw new DomainException('INVALID DATE FORMAT');
        }
        $supportedCardTypes = array_keys($pos->getCardTypeMapping());
        if ($supportedCardTypes !== [] && empty($cardType)) {
            throw new CardTypeRequiredException($pos::NAME);
        }
        if ($supportedCardTypes !== [] && !in_array($cardType, $supportedCardTypes)) {
            throw new CardTypeNotSupportedException($cardType);
        }

        return new CreditCard($number, $expDate, $cvv, $cardHolderName, $cardType);
    }
}
