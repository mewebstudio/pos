<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use DateTimeImmutable;
use DomainException;
use Exception;
use Mews\Pos\Entity\Card\CreditCard;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\CardTypeNotSupportedException;
use Mews\Pos\Exceptions\CardTypeRequiredException;
use Mews\Pos\PosInterface;

/**
 * CreditCardFactory
 */
class CreditCardFactory
{
    /**
     * @phpstan-param CreditCardInterface::CARD_TYPE_* $cardType
     *
     * @param PosInterface $pos
     * @param string       $number      credit card number with or without spaces
     * @param string       $expireYear  accepts year in 1, 2 and 4 digit format. accepted year formats '1' (2001), '02' (2002), '20' (2020), '2024' (2024)
     * @param string       $expireMonth single digit or double digit month values are accepted
     * @param string       $cvv
     * @param string|null  $cardHolderName
     * @param string|null  $cardType    bankaya gore zorunlu
     *
     * @return CreditCardInterface
     *
     * @throws CardTypeRequiredException
     * @throws CardTypeNotSupportedException
     * @throws Exception
     */
    public static function createForGateway(
        PosInterface $pos,
        string       $number,
        string       $expireYear,
        string       $expireMonth,
        string       $cvv,
        ?string      $cardHolderName = null,
        ?string      $cardType = null
    ): CreditCardInterface {
        $card = self::create($number, $expireYear, $expireMonth, $cvv, $cardHolderName, $cardType);

        $supportedCardTypes = \array_keys($pos->getCardTypeMapping());
        if ([] !== $supportedCardTypes) {
            if (null === $cardType) {
                throw new CardTypeRequiredException(\get_class($pos));
            }

            if (!\in_array($cardType, $supportedCardTypes, true)) {
                throw new CardTypeNotSupportedException($cardType);
            }
        }

        return $card;
    }


    /**
     * @phpstan-param CreditCardInterface::CARD_TYPE_* $cardType
     *
     * @param string      $number      credit card number with or without spaces
     * @param string      $expireYear  accepts year in 1, 2 and 4 digit format. accepted year formats '1' (2001), '02' (2002), '20' (2020), '2024' (2024)
     * @param string      $expireMonth single digit or double digit month values are accepted
     * @param string      $cvv
     * @param string|null $cardHolderName
     * @param string|null $cardType    bankaya gore zorunlu
     *
     * @return CreditCardInterface
     *
     * @throws CardTypeRequiredException
     * @throws CardTypeNotSupportedException
     * @throws Exception
     */
    public static function create(
        string  $number,
        string  $expireYear,
        string  $expireMonth,
        string  $cvv,
        ?string $cardHolderName = null,
        ?string $cardType = null
    ): CreditCardInterface {

        $number = \preg_replace('/\s+/', '', $number);
        if (null === $number) {
            throw new DomainException(\sprintf('Bad credit card number %s', $number));
        }

        $expDate = self::createDate($expireYear, $expireMonth);

        return new CreditCard($number, $expDate, $cvv, $cardHolderName, $cardType);
    }

    /**
     * @param string $expireYear  accepts year in 1, 2 and 4 digit format. accepted year formats '1' (2001), '02', (2002), '20' (2020), '2024' (2024)
     *
     * @param string $expireMonth single digit or double digit month values are accepted
     *
     * @return DateTimeImmutable
     */
    private static function createDate(string $expireYear, string $expireMonth): DateTimeImmutable
    {
        $expireYear = \str_pad($expireYear, 2, '0', STR_PAD_LEFT);
        $expireYear = \str_pad($expireYear, 4, '20', STR_PAD_LEFT);

        $expireMonth = \str_pad($expireMonth, 2, '0', STR_PAD_LEFT);
        $expDate     = DateTimeImmutable::createFromFormat('Ymd', $expireYear.$expireMonth.'01');

        if (!$expDate instanceof DateTimeImmutable) {
            throw new DomainException('INVALID DATE FORMAT');
        }

        return $expDate;
    }
}
