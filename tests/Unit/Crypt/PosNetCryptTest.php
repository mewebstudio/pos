<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\PosNetCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class PosNetCryptTest extends TestCase
{
    public PosNetCrypt $crypt;

    private PosNetAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            '67005551',
            '27426',
            PosInterface::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PosNetCrypt($logger);
    }

    public function testCreate3DHashException(): void
    {
        $this->expectException(NotImplementedException::class);

        $this->crypt->create3DHash($this->account, []);
    }

    public function testCreateHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->createHash($account, []);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, array $order, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData, $order);

        $this->assertSame($expected, $actual);
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['amount'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'mid' => '6706598320',
                    'tid' => '67005551',
                ],
                'order'       => [
                    'id'          => 'TST_190620093100_024',
                    'amount'      => 175,
                    'installment' => 0,
                    'currency'    => 'TL',
                ],
                'expected'    => 'nyeFSQ4J9NZVeCcEGCDomM8e2YIvoeIa/IDh2D3qaL4=',
            ],
        ];
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'xid'            => '00000000000000000895',
                    'amount'         => '100',
                    'currency'       => 'TL',
                    'installment'    => '00',
                    'point'          => '0',
                    'pointAmount'    => '0',
                    'txStatus'       => 'N',
                    'mdStatus'       => '9',
                    'mdErrorMessage' => 'None 3D - Secure Transaction',
                    'mac'            => '7I9ojRm7yzvZZTFNOYWocGIGSTv2Vmq23STR6X6X+c0=',
                ],
            ],
        ];
    }
}
