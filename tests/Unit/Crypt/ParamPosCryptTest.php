<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Crypt;

use Mews\Pos\Crypt\ParamPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ParamPosRequestDataMapperTest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Mews\Pos\Crypt\ParamPosCrypt
 * @covers \Mews\Pos\Crypt\AbstractCrypt
 */
class ParamPosCryptTest extends TestCase
{
    /**
     * @var AbstractPosAccount&MockObject
     */
    private AbstractPosAccount $account;

    private ParamPosCrypt $crypt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = $this->createMock(ParamPosAccount::class);
        $logger        = $this->createMock(LoggerInterface::class);
        $this->crypt   = new ParamPosCrypt($logger);
    }

    public function testCreate3DHash(): void
    {
        $this->expectException(NotImplementedException::class);
        $this->crypt->create3DHash($this->account, []);
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHash(array $responseData): void
    {
        $this->account->expects($this->any())
            ->method('getClientId')
            ->willReturn('10738');
        $this->account->expects($this->atLeastOnce())
            ->method('getStoreKey')
            ->willReturn('0c13d406-873b-403b-9c09-a5766840d98c');

        $this->assertTrue($this->crypt->check3DHash($this->account, $responseData));

        if (isset($responseData['TURKPOS_RETVAL_Hash'])) {
            $responseData['TURKPOS_RETVAL_Siparis_ID'] = '';
            $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));

            return;
        }

        $responseData['mdStatus'] = '';
        $this->assertFalse($this->crypt->check3DHash($this->account, $responseData));
    }

    /**
     * @dataProvider threeDHashCheckDataProvider
     */
    public function testCheck3DHashException(array $responseData): void
    {
        $account = $this->createMock(AbstractPosAccount::class);
        $this->expectException(\LogicException::class);
        $this->crypt->check3DHash($account, $responseData);
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
                'responseData' => [
                    'md'                => '581877:A65A349B0BAE27FC6567294215158DD8AE223843B5C96462F04A750CA7E8B165:3680:##500100000',
                    'mdStatus'          => '1',
                    'orderId'           => '2025011749D1',
                    'transactionAmount' => '10,01',
                    'islemGUID'         => '35513153-9902-4a4c-a256-af6ed9cadc52',
                    'islemHash'         => 'b1D7+nI3j4k3WGJuhW5IuPOFpEE=',
                    'bankResult'        => 'Y-status/Challenge authentication via ACS: https://3ds-acs.test.modirum.com/mdpayacs/p9w_3e2Ty_aWAjty/creq;token=340201161.17371  1',
                    'dc'                => '',
                    'dcURL'             => 'https://test-dmz.param.com.tr/turkpos.ws/service_turkpos_test.asmx',
                ],
            ],
            '3d_pay'                     => [
                'responseData' => [
                    'TURKPOS_RETVAL_Islem_ID'          => '1944A39AD0AEA92E173D665B',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '19.01.2025 17:29:32',
                    'TURKPOS_RETVAL_Dekont_ID'         => '3007300695',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '20250119BACB',
                    'TURKPOS_RETVAL_Ext_Data'          => '|||||||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '0',
                    'TURKPOS_RETVAL_PB'                => 'TL',
                    'TURKPOS_RETVAL_KK_No'             => '581877******2285',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'LOpkL9J8vne8E2j0A0HKOhUWGhI=',
                    'TURKPOS_RETVAL_Islem_GUID'        => '77f11031-cce8-4131-bf95-142303732608',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847062',
                ],
            ],
            '3d_secure_foreign_currency' => [
                'responseData' => [
                    'TURKPOS_RETVAL_Islem_ID'          => '21C152499BA0369D94028E30',
                    'TURKPOS_RETVAL_Sonuc'             => '1',
                    'TURKPOS_RETVAL_Sonuc_Str'         => 'Odeme Islemi Basarili',
                    'TURKPOS_RETVAL_GUID'              => '0c13d406-873b-403b-9c09-a5766840d98c',
                    'TURKPOS_RETVAL_Islem_Tarih'       => '20.01.2025 17:30:41',
                    'TURKPOS_RETVAL_Dekont_ID'         => '3007301017',
                    'TURKPOS_RETVAL_Tahsilat_Tutari'   => '10,01',
                    'TURKPOS_RETVAL_Odeme_Tutari'      => '9,83',
                    'TURKPOS_RETVAL_Siparis_ID'        => '202501208059',
                    'TURKPOS_RETVAL_Ext_Data'          => '||||',
                    'TURKPOS_RETVAL_Banka_Sonuc_Kod'   => '0',
                    'TURKPOS_RETVAL_PB'                => 'EUR',
                    'TURKPOS_RETVAL_KK_No'             => '454671******7894',
                    'TURKPOS_RETVAL_Taksit'            => '0',
                    'TURKPOS_RETVAL_Hash'              => 'sUAKlp9jJ9lyDVV4C0+lAnkDC9I=',
                    'TURKPOS_RETVAL_Islem_GUID'        => 'a699b219-9e77-4fc4-b2e8-4fae3e0b0cd1',
                    'TURKPOS_RETVAL_SanalPOS_Islem_ID' => '6021847384',
                ],
            ],
        ];
    }


    public static function hashCreateDataProvider(): array
    {
        return [
            '3d_secure_payment'          => [
                'requestData' => [
                    'TP_WMD_UCD' => [
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
                ],
                'expected'    => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
            ],
            '3d_pay'                     => [
                'requestData' => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()['3d_pay']['expected']['soap:Body'],
                'expected'    => 'qsIY8qDvdTsnALe7AYJiEA5kY20=',
            ],
            '3d_pay_installment'         => [
                'requestData' => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()['3d_pay_installment']['expected']['soap:Body'],
                'expected'    => 'zdIBpbUnRfvlCIgHo01yfIfMXXQ=',
            ],
            '3d_secure_foreign_currency' => [
                'requestData' => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()['3d_secure_foreign_currency']['expected']['soap:Body'],
                'expected'    => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
            ],
            '3d_secure_pre_payment'      => [
                'requestData' => ParamPosRequestDataMapperTest::paymentRegisterRequestDataProvider()['3d_secure_pre_payment']['expected']['soap:Body'],
                'expected'    => 'LFZ+Sl0mW+ybGvLr1u0ehZoxhxM=',
            ],
        ];
    }
}
