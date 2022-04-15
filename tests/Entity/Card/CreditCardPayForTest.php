<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardPayFor;
use PHPUnit\Framework\TestCase;

class CreditCardPayForTest extends TestCase
{
    public function testExpDate()
    {
        $card = new CreditCardPayFor('1111222233334444', '02', '03', '111', 'ahmet mehmet', AbstractCreditCard::CARD_TYPE_VISA);
        $this->assertEquals('0302', $card->getExpirationDate());
    }
}
