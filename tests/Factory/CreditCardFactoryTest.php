<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Tests\Factory;

use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\CardTypeNotSupportedException;
use Mews\Pos\Exceptions\CardTypeRequiredException;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\GarantiPos;
use PHPUnit\Framework\TestCase;

/**
 * CreditCardFactoryTest
 */
class CreditCardFactoryTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreateSuccess()
    {
        $pos = $this->getMockBuilder(EstPos::class)->disableOriginalConstructor()
            //just any method
            ->onlyMethods(['send', 'getCardTypeMapping'])
            ->getMock();

        $card = CreditCardFactory::create(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '02',
            '123',
            'john',
            AbstractCreditCard::CARD_TYPE_VISA
        );

        $this->assertSame('4444555566667777', $card->getNumber());
        $this->assertSame('2022', $card->getExpireYear('Y'));
        $this->assertSame('02', $card->getExpireMonth('m'));
        $this->assertSame('202202', $card->getExpirationDate('Ym'));
        $this->assertSame('john', $card->getHolderName());
        $this->assertSame('123', $card->getCvv());
    }

    /**
     * @return void
     */
    public function testCreateWithEmptyTypeMapping()
    {
        $pos = $this->getMockBuilder(GarantiPos::class)->disableOriginalConstructor()
            //just any method
            ->onlyMethods(['send', 'getCardTypeMapping'])
            ->getMock();

        $card = CreditCardFactory::create(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '12',
            '123',
            'john',
            AbstractCreditCard::CARD_TYPE_VISA
        );
        $this->assertNotEmpty($card);
    }

    /**
     * @return void
     */
    public function testCreateUnSupportedCardTypeException()
    {
        $this->expectException(CardTypeNotSupportedException::class);
        $pos = $this->getMockBuilder(EstPos::class)->disableOriginalConstructor()->getMock();
        $pos->expects($this->once())->method('getCardTypeMapping')->willReturn(['visa' => 'visa']);

        CreditCardFactory::create(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '12',
            '123',
            'john',
            AbstractCreditCard::CARD_TYPE_AMEX
        );
    }

    /**
     * @return void
     */
    public function testCreateCardTypeRequiredException()
    {
        $this->expectException(CardTypeRequiredException::class);
        $pos = $this->getMockBuilder(EstPos::class)->disableOriginalConstructor()->getMock();

        $pos->expects($this->once())->method('getCardTypeMapping')->willReturn(['visa' => 'visa']);

        CreditCardFactory::create(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '12',
            '123',
            'john'
        );
    }

    /**
     * @return void
     */
    public function testCreateCardTypeIsNotRequired()
    {
        $pos = $this->getMockBuilder(GarantiPos::class)->disableOriginalConstructor()
            //just any method
            ->onlyMethods(['send', 'getCardTypeMapping'])
            ->getMock();

        $card = CreditCardFactory::create(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '12',
            '123',
            'john'
        );
        $this->assertNotEmpty($card);
    }
}
