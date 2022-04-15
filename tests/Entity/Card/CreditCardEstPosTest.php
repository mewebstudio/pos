<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardEstPos;
use PHPUnit\Framework\TestCase;

class CreditCardEstPosTest extends TestCase
{
    public function testGetCardCode()
    {
        $card = new CreditCardEstPos('1111222233334444', '02', '03', '111', 'ahmet mehmet', AbstractCreditCard::CARD_TYPE_VISA);
        $this->assertEquals('1', $card->getCardCode());
    }
}
