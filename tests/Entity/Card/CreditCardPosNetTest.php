<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\CreditCardPosNet;
use PHPUnit\Framework\TestCase;

class CreditCardPosNetTest extends TestCase
{
    public function testGetExpirationDate()
    {
        $card = new CreditCardPosNet('1111222233334444', '02', '03', '111', 'ahmet mehmet');
        $this->assertEquals('0203', $card->getExpirationDate());
    }
}
