<?php


namespace Mews\Pos\Tests\Entity\Card;

use Mews\Pos\Entity\Card\CreditCardVakifBank;
use PHPUnit\Framework\TestCase;

class CreditCardVakifBankTest extends TestCase
{
    public function testGetExpirationDate()
    {
        $card = new CreditCardVakifBank('1111222233334444', '02', '03', '111', 'ahmet mehmet');
        $this->assertEquals('0203', $card->getExpirationDate());
    }

    public function testTypeCode()
    {
        $card = new CreditCardVakifBank('1111222233334444', '02', '03', '111', 'ahmet mehmet', 'master');

        $this->assertEquals('200', $card->getCardCode());

        $card = new CreditCardVakifBank('1111222233334444', '02', '03', '111', 'ahmet mehmet', '200');
        $this->assertEquals('200', $card->getCardCode());
    }
}
