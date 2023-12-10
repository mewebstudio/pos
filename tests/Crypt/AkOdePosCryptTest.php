<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\AkOdePosCrypt;
use Mews\Pos\Entity\Account\AkOdePosAccount;
use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * @covers \Mews\Pos\Crypt\AkOdePosCrypt
 */
class AkOdePosCryptTest extends TestCase
{
    private AkOdePosAccount $account;

    private AkOdePosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createAkOdePosAccount(
            'akode',
            '1000000494',
            'POS_ENT_Test_001',
            'POS_ENT_Test_001!*!*',
        );

        $this->crypt = new AkOdePosCrypt(new NullLogger());
    }

    public function testCreate3DHash(): void
    {
        $requestData = [
            'timeSpan' => '20231209201121',
            'rnd'      => 'rand',
        ];
        $expected    = 'BwZ05tt0aNgIgtrrqmlTwSIaeetpQyyGLH6xTsQbHae7ANCIVKmLHPxYWk5XP3Li5fr4La1bZS9/43OihP0dig==';

        $actual = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertEquals($expected, $actual);
    }

    public function testCreateHash(): void
    {
        $requestData = [
            'timeSpan' => '20231209201121',
            'rnd'      => 'rand',
        ];
        $expected    = 'BwZ05tt0aNgIgtrrqmlTwSIaeetpQyyGLH6xTsQbHae7ANCIVKmLHPxYWk5XP3Li5fr4La1bZS9/43OihP0dig==';

        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertEquals($expected, $actual);
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

    public function threeDHashCheckDataProvider(): array
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
