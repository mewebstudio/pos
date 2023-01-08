<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PosNetCryptTest extends TestCase
{
    /**
     * @var PosNetCrypt
     */
    private $threeDAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createPosNetAccount(
            'yapikredi',
            '6706598320',
            'XXXXXX',
            'XXXXXX',
            '67005551',
            '27426',
            AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->crypt = new PosNetCrypt(new NullLogger());
    }


    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $txType, string $expected)
    {
        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData, $txType);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData)
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->threeDAccount, $responseData));

        $responseData['amount'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    /**
     * @return void
     */
    public function testCreateSecurityData()
    {
        $this->assertSame('c1PPl+2UcdixyhgLYnf4VfJyFGaNQNOwE0uMkci7Uag=', $this->crypt->createSecurityData($this->threeDAccount));
    }

    public function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id' => 'TST_190620093100_024',
                    'name' => 'siparis veren',
                    'email' => 'test@test.com',
                    'amount' => 175,
                      'installment' => 0,
                      'currency' => 'TL',
                      'success_url' => 'https://domain.com/success',
                      'fail_url' => 'https://domain.com/fail_url',
                      'rand' => '0.43625700 1604831630',
                      'lang' => 'tr',

                ],
                'txType' => 'Sale',
                'expected'    => 'nyeFSQ4J9NZVeCcEGCDomM8e2YIvoeIa/IDh2D3qaL4=',
            ],
        ];
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'xid' => '00000000000000000895',
                    'amount' => '100',
                    'currency' => 'TL',
                    'installment' => '00',
                    'point' => '0',
                    'pointAmount' => '0',
                    'txStatus' => 'N',
                    'mdStatus' => '9',
                    'mdErrorMessage' => 'None 3D - Secure Transaction',
                    'mac'        => '7I9ojRm7yzvZZTFNOYWocGIGSTv2Vmq23STR6X6X+c0=',
                ],
            ],
        ];
    }

}
