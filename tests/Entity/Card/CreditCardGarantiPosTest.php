<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardGarantiPos;
use PHPUnit\Framework\TestCase;

class CreditCardGarantiPosTest extends TestCase
{
    public function testExpDate()
    {
        $card = new CreditCardGarantiPos('1111222233334444', '02', '03', '111', 'ahmet mehmet', AbstractCreditCard::CARD_TYPE_VISA);
        $this->assertEquals('0302', $card->getExpirationDate());
    }
}
