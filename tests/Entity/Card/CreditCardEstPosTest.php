<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\CreditCardEstPos;
use PHPUnit\Framework\TestCase;

class CreditCardEstPosTest extends TestCase
{
    public function testGetCardCode()
    {
        $card = new CreditCardEstPos('1111222233334444', '02', '03', '111', 'ahmet mehmet', 'visa');
        $this->assertEquals('1', $card->getCardCode());
    }

}