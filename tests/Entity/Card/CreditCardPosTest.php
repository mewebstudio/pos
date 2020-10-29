<?php

namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\CreditCardPos;
use PHPUnit\Framework\TestCase;

class CreditCardPosTest extends TestCase
{
    /**
     * tests created card
     */
    public function testCreate()
    {
        $card = new CreditCardPos('1111222233334444', '02', '03', '111', 'ahmet mehmet');
        $this->assertEquals('1111222233334444', $card->getNumber());
        $this->assertEquals('02', $card->getExpireYear());
        $this->assertEquals('03', $card->getExpireMonth());
        $this->assertEquals('0302', $card->getExpirationDate());
        $this->assertEquals('111', $card->getCvv());
        $this->assertEquals('ahmet mehmet', $card->getHolderName());

        $card = new CreditCardPos(' 1111 2222    3333   4444 ', '2022', '05', '111');
        $this->assertEquals('1111222233334444', $card->getNumber());
        $this->assertEquals('22', $card->getExpireYear());
        $this->assertEquals('05', $card->getExpireMonth());
        $this->assertEquals('0522', $card->getExpirationDate());

        $card = new CreditCardPos('1111222233334444', 21, 5, '111');
        $this->assertEquals('21', $card->getExpireYear());
        $this->assertEquals('05', $card->getExpireMonth());
        $this->assertEquals('0521', $card->getExpirationDate());
    }
}