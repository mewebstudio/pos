<?php

namespace Mews\Pos\Tests\Crypt;

use Mews\Pos\Crypt\PosNetV1PosCrypt;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Factory\AccountFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PosNetV1PosCryptTest extends TestCase
{
    /** @var PosNetV1PosCrypt */
    public $crypt;

    /** @var PosNetAccount */
    private $threeDAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->threeDAccount = AccountFactory::createPosNetAccount(
            'albaraka',
            '6700950031',
            '67540050',
            '1010272261352072',
            \Mews\Pos\Gateways\AbstractGateway::MODEL_3D_SECURE,
            '10,10,10,10,10,10,10,10'
        );

        $this->crypt = new PosNetV1PosCrypt(new NullLogger());
    }

    /**
     * @dataProvider hashFromParamsDataProvider
     */
    public function testHashFromParams(string $storeKey, array $data, string $expected)
    {
        $this->assertSame($expected, $this->crypt->hashFromParams($storeKey, $data, 'MACParams', ':'));
    }

    /**
     * @dataProvider hashCreateDataProvider
     */
    public function testCreateHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->createHash($this->threeDAccount, $requestData);
        $this->assertEquals($expected, $actual);
    }


    /**
     * @dataProvider threeDHashCreateDataProvider
     */
    public function testCreate3DHash(array $requestData, string $expected)
    {
        $actual = $this->crypt->create3DHash($this->threeDAccount, $requestData);

        $this->assertSame($expected, $actual);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(bool $expected, array $responseData)
    {
        $this->assertSame($expected, $this->crypt->check3DHash($this->threeDAccount, $responseData));
    }

    public static function threeDHashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'MerchantNo'  => '6700950031',
                    'TerminalNo'  => '67540050',
                    'Amount'      => '175',
                    'CardNo'      => '5400619360964581',
                    'ExpiredDate' => '2001',
                    'Cvv'         => '056',
                ],
                'expected'    => 'xuhPbpcPJ6kVs7JeIXS8f06Cv0mb9cNPMfjp1HiB7Ew=',
            ],
        ];
    }

    public static function hashFromParamsDataProvider(): array
    {
        return [
            [
                'storeKey'  => '10,10,10,10,10,10,10,10',
                'data '     => [
                    'MACParams'     => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
                    'MerchantNo'    => '6700950031',
                    'TerminalNo'    => '67540050',
                    'ReferenceCode' => '021459486690000191',
                    'OrderId'       => null,
                ],
                'expected'  => 'qhLo/2Ro+vT81i0SMV/VHifDV9VzQQgK+7d8hlId9YM=',
            ],
            [
                'storeKey'  => '10,10,10,10,10,10,10,10',
                'requestData' => [
                    'MerchantNo'  => '6700950031',
                    'TerminalNo'  => '67540050',
                    'MACParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                    'CardInformationData' => [
                        'Amount'      => '175',
                        'CardNo'      => '5400619360964581',
                        'ExpireDate' => '2001',
                        'Cvc2'         => '056',
                    ]
                ],
                'expected'    => 'xuhPbpcPJ6kVs7JeIXS8f06Cv0mb9cNPMfjp1HiB7Ew=',
            ],
        ];
    }

    public static function threeDHashCheckDataProvider(): array
    {
        return [
            [
                'expectedResult' => true,
                'responseData'   => [
                    'ECI'                 => '02',
                    'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                    'MdStatus'            => '1',
                    'MdErrorMessage'      => 'Authenticated',
                    'MD'                  => '0161010028947569644,0161010028947569644',
                    'SecureTransactionId' => '1010028947569644',
                    'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                    'OrderId'             => '123',
                ],
            ],
            [
                'expectedResult' => false,
                'responseData'   => [
                    'ECI'                 => '',
                    'CAVV'                => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                    'MdStatus'            => '1',
                    'MdErrorMessage'      => 'Authenticated',
                    'MD'                  => '0161010028947569644,0161010028947569644',
                    'SecureTransactionId' => '1010028947569644',
                    'Mac'                 => 'r21kMm4nMqvJakjq47Jl+3fk2xrFPrDoTJFQGxkgkfk=',
                    'MacParams'           => 'ECI:CAVV:MdStatus:MdErrorMessage:MD:SecureTransactionId',
                    'OrderId'             => '123',
                ],
            ],
        ];
    }

    public static function hashCreateDataProvider(): array
    {
        return [
            [
                'requestData' => [
                    'ThreeDSecureData' => [
                        'MerchantNo'          => '6700950031',
                        'TerminalNo'          => '67540050',
                        'SecureTransactionId' => '1010028947569644',
                        'CavvData'            => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                        'Eci'                 => '02',
                        'MdStatus'            => '1',
                    ],
                ],
                'expected'    => 'kAKxvbwXvmrM6lapGx1UcRTs454tsSuPrBXV7oA7L7w=',
            ],
            [
                'requestData' => [
                    'ThreeDSecureData' => [
                        'MerchantNo'          => '6700950031',
                        'TerminalNo'          => '67540050',
                        'SecureTransactionId' => '1010028947569644',
                        'CavvData'            => 'jKOBaLBL3hQ+CREBPu1HBQQAAAA=',
                        'Eci'                 => '02',
                        'MdStatus'            => '',
                    ],
                ],
                'expected'    => 'RRYxriTO++OHc4cQ3VIp0z9HMrFy/Msm3Dw2t+sEXCA=',
            ],
        ];
    }
}
