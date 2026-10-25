<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\AbstractCrypt;
use Mews\Pos\Crypt\PayForPosCrypt;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Exception\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\PayForPos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(PayForPosCrypt::class)]
#[CoversClass(AbstractCrypt::class)]
class PayForPosCryptTest extends TestCase
{
    public PayForPosCrypt $crypt;

    private PayForPosAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createPayForPosAccount(
            'qnbfinansbank-payfor',
            '085300000009704',
            'QNB_API_KULLANICI_3DPAY',
            'UcBN0',
            '12345678'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new PayForPosCrypt($logger);
    }

    public function testSupports(): void
    {
        $supports = $this->crypt::supports(PayForPos::class);
        $this->assertTrue($supports);

        $supports = $this->crypt::supports(AssecoPos::class);
        $this->assertFalse($supports);
    }

    #[DataProvider('threeDHashCreateDataProvider')]
    public function testCreate3DHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->create3DHash($this->account, $requestData);

        $this->assertSame($expected, $actual);
    }

    #[DataProvider('threeDHashCheckDataProvider')]
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
            // 3dpay
            [
                'expectedResult' => true,
                'responseData'   => [
                    'OrderId'        => '2022103114B3',
                    'AuthCode'       => 'S86797',
                    'ProcReturnCode' => '00',
                    '3DStatus'       => '1',
                    'ResponseRnd'    => 'PF638028538035077529',
                    'ResponseHash'   => 'il/SWNG2llQkLgnCbUG6lPYtlgM=',
                ],
            ],
            // 3dhost
            [
                'expectedResult' => true,
                'responseData'   => [
                    'OrderId'        => '2022103121CA',
                    'AuthCode'       => 'S28031',
                    'ProcReturnCode' => '00',
                    '3DStatus'       => '1',
                    'ResponseRnd'    => 'PF638028543970517651',
                    'ResponseHash'   => 'R3sOuVFWDP8nvu9mtVtvLxwlqow=',
                ],
            ],
        ];
    }
}
