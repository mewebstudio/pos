<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\DataMapper\RequestDataMapper\ParamPosRequestDataMapper;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\ParamPosSerializer;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\ParamPosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\ParamPosSerializer
 */
class ParamPosSerializerTest extends TestCase
{
    private ParamPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new ParamPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(ParamPos::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $txType, string $expected): void
    {
        $result   = $this->serializer->encode($data, $txType);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $result);
    }


    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $data, string $txType, array $expected): void
    {
        $result = $this->serializer->decode($data, $txType);
        if (isset($result['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'])) {
            $result['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'] = $expected['TP_WMD_UCDResponse']['TP_WMD_UCDResult']['UCD_HTML'];
        }

        $this->assertSame($expected, $result);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => [
                'Islem_ID'           => 'rand',
                'Islem_Hash'         => 'jsLYSB3lJ81leFgDLw4D8PbXURs=',
                'G'                  => [
                    'CLIENT_CODE'     => '10738',
                    'CLIENT_USERNAME' => 'Test1',
                    'CLIENT_PASSWORD' => 'Test2',
                ],
                'GUID'               => '0c13d406-873b-403b-9c09-a5766840d98c',
                'Islem_Guvenlik_Tip' => '3D',
            ],
            'txType'   => 'TP_WMD_UCD',
            'expected' => '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body><TP_WMD_UCD xmlns="https://turkpos.com.tr/"><Islem_ID>rand</Islem_ID><Islem_Hash>jsLYSB3lJ81leFgDLw4D8PbXURs=</Islem_Hash><G><CLIENT_CODE>10738</CLIENT_CODE><CLIENT_USERNAME>Test1</CLIENT_USERNAME><CLIENT_PASSWORD>Test2</CLIENT_PASSWORD></G><GUID>0c13d406-873b-403b-9c09-a5766840d98c</GUID><Islem_Guvenlik_Tip>3D</Islem_Guvenlik_Tip></TP_WMD_UCD></soap:Body></soap:Envelope>
',
        ];
    }

    public static function decodeDataProvider(): Generator
    {
        yield '3d_form_success' => [
            'input'    => \file_get_contents(__DIR__.'/../test_data/parampos/3d_form_response_success.xml'),
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => [
                'TP_WMD_UCDResponse' => [
                    'TP_WMD_UCDResult' => [
                        'Islem_ID'        => '6021840768',
                        'Islem_GUID'      => 'd68ac15c-17ca-4b7d-a046-10700291b249',
                        'UCD_HTML'        => 'html-document',
                        'UCD_MD'          => 'MosNOirpqxod2A0BdoPpFNf7E/hJX2pKvt8hunrQF2RSrggeWpNj9p+XDEgRdWfGdtGMHF5A7X/uVbJTb3cCN5LGcG2JsGd69bXc7yYBGGw/VMFTcHDObj+cVR6fP2k1s531ozcBEFN1hv+fwBH80YGHP2a6xbRujYzME2iPuPgCdr7wkoSWcZvwB5M73bFow3Jx3vqkwceaPUO6dat7m5Uv1dKmbp+py3yOR0nVaFGnKTmIB4JIAIuP24hCU2MJi+hvKDf7+IJIEl5cjotiUx/J0AINoeuIGrklDAZ8JRA7pxYXpZLwc3ZX60VpWvfS7sSOdayadMBOvltQSdRrPPhJztVNmkztgUe7s3rbpdVr4Fc/KzGtPa5PZLnpkXszhOO4g+pw0A3KuFsqTdFuuu25CqBTX/aG4yZ4VO7UKfG27cTgRaObKsU+YiwOhH/VgGODvd5qrR02gOY8f9Xqtw==',
                        'Sonuc'           => '1',
                        'Sonuc_Str'       => 'İşlem Başarılı',
                        'Banka_Sonuc_Kod' => '0',
                        'Siparis_ID'      => '20241229D2FF',
                    ],
                ],
            ],
        ];
    }
}
