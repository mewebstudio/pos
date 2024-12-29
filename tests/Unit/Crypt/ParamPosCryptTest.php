<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\ParamPosCrypt;
use Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Factory\AccountFactory;
use Mews\Pos\PosInterface;
use Mews\Pos\Tests\Unit\DataMapper\ResponseDataMapper\ParamPosResponseDataMapperTest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\ParamPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class ParamPosCryptTest extends TestCase
{
    private ParamPosAccount $account;

    private ParamPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = AccountFactory::createParamPosAccount(
            'param-pos',
            10738,
            'Test',
            'Test',
            '0c13d406-873b-403b-9c09-a5766840d98c'
        );

        $logger      = $this->createMock(LoggerInterface::class);
        $this->crypt = new ParamPosCrypt($logger);
    }

    public function testCreate3DHash(): void
    {
        $requestData = [
            'clientid'  => '700655000200',
            'oid'       => 'order222',
            'amount'    => '100.25',
            'taksit'    => '',
            'islemtipi' => 'Auth',
            'okUrl'     => 'https://domain.com/success',
            'failUrl'   => 'https://domain.com/fail_url',
            'rnd'       => 'rand',
        ];
        $expected    = 'S7UxUAohxaxzl35WxHyDfuQx0sg=';

        $actual = $this->crypt->create3DHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData): void
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->account, $responseData));

        $responseData['mdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    public function testCheck3DHashException(): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, []);
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected): void
    {
        $actual = $this->crypt->createHash($this->account, $requestData);
        $this->assertSame($expected, $actual);
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => ParamPosResponseDataMapperTest::threeDPaymentDataProvider()['success1']['threeDResponseData'],
            ],
        ];
    }


    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'G'                  => [
                        'CLIENT_CODE'     => '10738',
                        'CLIENT_USERNAME' => 'Test',
                        'CLIENT_PASSWORD' => 'Test',
                    ],
                    'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'Islem_Guvenlik_Tip' => '3D',
                    'Islem_ID'           => '4FF84F9EA8BE63C25EFB15A2',
                    'IPAdr'              => '192.168.192.1',
                    'Siparis_ID'         => '202412293F4E',
                    'Islem_Tutar'        => '1.000,01',
                    'Toplam_Tutar'       => '1.000,01',
                    'Basarili_URL'       => 'http://localhost/parampos/3d/response.php',
                    'Hata_URL'           => 'http://localhost/parampos/3d/response.php',
                    'Taksit'             => '3',
                    'KK_Sahibi'          => 'John Doe',
                    'KK_No'              => '4446763125813623',
                    'KK_SK_Ay'           => '12',
                    'KK_SK_Yil'          => '2026',
                    'KK_CVC'             => '000',
                    'KK_Sahibi_GSM'      => '',
                ],
                'expected'    => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
            ],
        ];
    }
}
