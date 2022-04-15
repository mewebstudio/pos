<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardInterPos;
use PHPUnit\Framework\TestCase;

class CreditCardInterPosTest extends TestCase
{
    public function testExpDate()
    {
        $card = new CreditCardInterPos('1111222233334444', '02', '03', '111', 'ahmet mehmet', AbstractCreditCard::CARD_TYPE_VISA);
        $this->assertEquals('0302', $card->getExpirationDate());
    }

    public function testGetCardCode()
    {
        $card = new CreditCardInterPos('1111222233334444', '02', '03', '111', 'ahmet mehmet', AbstractCreditCard::CARD_TYPE_MASTERCARD);
        $this->assertEquals('1', $card->getCardCode());
    }
}
