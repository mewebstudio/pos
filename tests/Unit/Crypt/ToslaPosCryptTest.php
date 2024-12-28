<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\ToslaPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\ToslaPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class ToslaPosCryptTest extends TestCase
{
    private ToslaPosAccount $account;

    private ToslaPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createToslaPosAccount(
            'tosla',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new ToslaPosCrypt($logger);
    }

    public function testCreate3DHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->create3DHash($this->account, []);
    }

    public function testCreateHash(): void
    {
        $requestData = [
            'clientId' => '1000000494',
            'apiUser'  => 'POS_ENT_Test_001',
            'timeSpan' => '20231209201121',
            'rnd'      => 'rand',
        ];
        $expected    = 'BwZ05tt0aNgIgtrrqmlTwSIaeetpQyyGLH6xTsQbHae7ANCIVKmLHPxYWk5XP3Li5fr4La1bZS9/43OihP0dig==';

        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(array $responseData): void
    {
        $this->assertTrue($this->crypt->check3DHash($this->account, $responseData));

        $responseData['MdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'paymentData' => [
                    'ClientId'            => '1000000494',
                    'OrderId'             => '202312034E91',
                    'MdStatus'            => '1',
                    'ThreeDSessionId'     => 'P40D18956D9C94188ABF6C87B37075AF7B1029577C4BF4BADB8E86058919000F4',
                    'BankResponseCode'    => '00',
                    'BankResponseMessage' => '',
                    'RequestStatus'       => '1',
                    'HashParameters'      => 'ClientId,ApiUser,OrderId,MdStatus,BankResponseCode,BankResponseMessage,RequestStatus',
                    'Hash'                => 'CgibjWkLpfx+Cz6cVlbH1ViSW74ouKACVOW0Vrt2SfqPMt+V3hfIx/4LnOgcInFhPci/qcnIMgdN0RptHSmFOg==',
                ],
            ],
        ];
    }
}
