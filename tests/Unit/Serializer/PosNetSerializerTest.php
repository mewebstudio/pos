<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Serializer\PosNetSerializer;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\PosNetSerializer
 */
class PosNetSerializerTest extends TestCase
{
    private PosNetSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new PosNetSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(PosNet::class);

        $this->assertTrue($supports);
    }

    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, ?string $format, string $expectedFormat, $expected): void
    {
        $result   = $this->serializer->encode($data, null, $format);
        $expected = str_replace(["\r"], '', $expected);

        $this->assertSame($expected, $result->getData());
        $this->assertSame($expectedFormat, $result->getFormat());
    }

    /**
     * @dataProvider decodeXmlDataProvider
     */
    public function testDecodeXML(string $input, array $expected): void
    {
        $actual = $this->serializer->decode($input);

        $this->assertSame($expected, $actual);
    }

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'           => [
                'mid'              => '6706598320',
                'tid'              => '67005551',
                'tranDateRequired' => '1',
                'sale'             => [
                    'orderID'      => '0000190620093100_024',
                    'installment'  => '00',
                    'amount'       => 175,
                    'currencyCode' => 'TL',
                    'ccno'         => '5555444433332222',
                    'expDate'      => '2112',
                    'cvc'          => '122',
                ],
            ],
            'format'          => null,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<?xml version="1.0" encoding="ISO-8859-9"?>
<posnetRequest><mid>6706598320</mid><tid>67005551</tid><tranDateRequired>1</tranDateRequired><sale><orderID>0000190620093100_024</orderID><installment>00</installment><amount>175</amount><currencyCode>TL</currencyCode><ccno>5555444433332222</ccno><expDate>2112</expDate><cvc>122</cvc></sale></posnetRequest>
',
        ];

        yield 'test2' => [
            'input'           => [
                'mid'              => '6706598320',
                'tid'              => '67005551',
                'tranDateRequired' => '1',
                'sale'             => [
                    'orderID'      => '0000190620093100_024',
                    'installment'  => '00',
                    'amount'       => 175,
                    'currencyCode' => 'TL',
                    'ccno'         => '5555444433332222',
                    'expDate'      => '2112',
                    'cvc'          => '122',
                ],
            ],
            'format'          => SerializerInterface::FORMAT_XML,
            'expected_format' => SerializerInterface::FORMAT_XML,
            'expected'        => '<?xml version="1.0" encoding="ISO-8859-9"?>
<posnetRequest><mid>6706598320</mid><tid>67005551</tid><tranDateRequired>1</tranDateRequired><sale><orderID>0000190620093100_024</orderID><installment>00</installment><amount>175</amount><currencyCode>TL</currencyCode><ccno>5555444433332222</ccno><expDate>2112</expDate><cvc>122</cvc></sale></posnetRequest>
',
        ];

        yield 'test4' => [
            'input'           => ['xmldata' => '<?xml version="1.0" encoding="ISO-8859-9"?>'],
            'format'          => SerializerInterface::FORMAT_FORM,
            'expected_format' => SerializerInterface::FORMAT_FORM,
            'expected'        => 'xmldata=%3C%3Fxml+version%3D%221.0%22+encoding%3D%22ISO-8859-9%22%3F%3E',
        ];
    }

    public static function decodeXmlDataProvider(): iterable
    {
        yield [
            'input'    => "<?xml version='1.0' encoding='iso-8859-9'?><posnetResponse><approved>0</approved><respCode>0148</respCode><respText>INVALID MID TID IP. Hatalý IP:88.152.9.140</respText></posnetResponse>",
            'expected' => [
                'approved' => '0',
                'respCode' => '0148',
                'respText' => 'INVALID MID TID IP. HatalÃ½ IP:88.152.9.140',
            ],
        ];
    }
}
