<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\PayForPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class PayForPosCryptTest extends TestCase
{
    public PayForPosCrypt $crypt;

    private PayForAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            PosInterface::MODEL_3D_SECURE,
            '12345678'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PayForPosCrypt($logger);
    }


    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['3DStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCreateHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->createHash($this->account, []);
    }

    public static function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MbrId'            => '5',
                    'OrderId'          => '2020110828BC',
                    'PurchAmount'      => 100.01,
                    'TxnType'          => 'Auth',
                    'InstallmentCount' => '0',
                    'OkUrl'            => 'http://localhost/finansbank-payfor/3d/response.php',
                    'FailUrl'          => 'http://localhost/finansbank-payfor/3d/response.php',
                    'Rnd'              => '0.43625700 1604831630',
                ],
                'expected'    => 'zmSUxYPhmCj7QOzqpk/28LuE1Oc=',
            ],
        ];
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'OrderId'        => '20221031FD04',
                    'AuthCode'       => '',
                    'ProcReturnCode' => 'V033',
                    '3DStatus'       => '1',
                    'ResponseRnd'    => 'PF638028511007418219',
                    'ResponseHash'   => 'rVcKoOOl3jKukGLHcQaVM6ZuznU=',
                ],
            ],
        ];
    }

}
