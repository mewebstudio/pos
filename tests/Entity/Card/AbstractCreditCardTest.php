<?php

namespace Mews\Pos\Tests\Entity\Card;

use DomainException;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Entity\Card\AbstractCreditCard
 */
class AbstractCreditCardTest extends TestCase
{
    public function testInvalidDateException()
    {
        $testData = [
            'number' => '1234567890123456',
            'expireYear' => '',
            'expireMonth' => '',
            'cvv' => '123',
        ];
        $this->expectException(DomainException::class);
        $this->getMockForAbstractClass(AbstractCreditCard::class, $testData);
    }
}
