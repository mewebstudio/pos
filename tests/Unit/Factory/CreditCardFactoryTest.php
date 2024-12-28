<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Factory;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\CardTypeNotSupportedException;
use Mews\Pos\Exceptions\CardTypeRequiredException;
use Mews\Pos\Factory\CreditCardFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Factory\CreditCardFactory
 */
class CreditCardFactoryTest extends TestCase
{
    /**
     * @return void
     */
    public function testCreateForGatewaySuccess(): void
    {
        $pos = $this->createMock(PosInterface::class);
        $pos->expects(self::once())
            ->method('getCardTypeMapping')
            ->willReturn([CreditCardInterface::CARD_TYPE_VISA => 'visa']);

        $card = CreditCardFactory::createForGateway(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '02',
            '123',
            'john',
            CreditCardInterface::CARD_TYPE_VISA
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
    public function testCreateForGatewayUnSupportedCardTypeException(): void
    {
        $this->expectException(CardTypeNotSupportedException::class);
        $pos = $this->createMock(PosInterface::class);
        $pos->expects(self::once())
            ->method('getCardTypeMapping')
            ->willReturn([CreditCardInterface::CARD_TYPE_VISA => 'visa']);

        CreditCardFactory::createForGateway(
            $pos,
            '4444 5555 6666 7777',
            '22',
            '12',
            '123',
            'john',
            CreditCardInterface::CARD_TYPE_AMEX
        );
    }

    /**
     * @return void
     */
    public function testCreateForGatewayCardTypeRequiredException(): void
    {
        $this->expectException(CardTypeRequiredException::class);

        $pos = $this->createMock(PosInterface::class);
        $pos->expects(self::once())
            ->method('getCardTypeMapping')
            ->willReturn([CreditCardInterface::CARD_TYPE_VISA => 'visa']);

        CreditCardFactory::createForGateway(
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
    public function testCreateForGatewayCardTypeIsNotRequired(): void
    {
        $pos = $this->createMock(PosInterface::class);
        $pos->expects(self::once())
            ->method('getCardTypeMapping')
            ->willReturn([]);

        $card = CreditCardFactory::createForGateway(
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
