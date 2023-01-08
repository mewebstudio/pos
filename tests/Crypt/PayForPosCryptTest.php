<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateways\AbstractGateway;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PayForPosCryptTest extends TestCase
{
    /**
     * @var PayForPosCrypt
     */
    private $threeDAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            AbstractGateway::MODEL_3D_SECURE,
            '12345678'
        );

        $this->order = [
            'id'          => '2020110828BC',
            'amount'      => 10.01,
            'installment' => '0',
            'currency'    => 'TRY',
            'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
            'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
            'rand'        => '0.43625700 1604831630',
            'hash'        => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            'ip'          => '127.0.0.1',
            'lang'        => AbstractGateway::LANG_TR,
        ];

        $this->crypt = new PayForPosCrypt(new NullLogger());
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

        $responseData['3DStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    public function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'id'          => '2020110828BC',
                    'amount'      => 100.01,
                    'installment' => '0',
                    'success_url' => 'http://localhost/finansbank-payfor/3d/response.php',
                    'fail_url'    => 'http://localhost/finansbank-payfor/3d/response.php',
                    'rand'        => '0.43625700 1604831630',
                ],
                'txType' => 'Auth',
                'expected'    => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            ],
        ];
    }

    public function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'OrderId' => '20221031FD04',
                    'AuthCode' => '',
                    'ProcReturnCode' => 'V033',
                    '3DStatus' => '1',
                    'ResponseRnd' => 'PF638028511007418219',
                    'ResponseHash' => 'rVcKoOOl3jKukGLHcQaVM6ZuznU=',
                ],
            ],
        ];
    }

}
