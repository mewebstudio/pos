<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Gateways\AkOdePos;
use Mews\Pos\Gateways\EstV3Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\AkOdePosSerializer;
use Mews\Pos\Tests\Unit\DataMapper\RequestDataMapper\AkOdePosRequestDataMapperTest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\AkOdePosSerializer
 */
class AkOdePosSerializerTest extends TestCase
{
    private AkOdePosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new AkOdePosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(AkOdePos::class);

        $this->assertTrue($supports);

        $supports = $this->serializer::supports(EstV3Pos::class);

        $this->assertFalse($supports);
    }


    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expected): void
    {
        $result = $this->serializer->encode($data);

        $this->assertSame($expected, $result);
    }


    /**
     * @dataProvider decodeDataProvider
     */
    public function testDecode(string $input, string $txType, array $expected): void
    {
        $actual = $this->serializer->decode($input, $txType);

        $this->assertSame($expected, $actual);
    }

    public static function encodeDataProvider(): array
    {
        return [
            [
                'input'    => AkOdePosRequestDataMapperTest::paymentRegisterRequestDataProvider()[0]['expected'],
                'expected' => '{"clientId":"1000000494","apiUser":"POS_ENT_Test_001","callbackUrl":"https:\/\/domain.com\/success","orderId":"order222","amount":10025,"currency":949,"installmentCount":0,"rnd":"rand","timeSpan":"20231209214708","hash":"+XGO1qv+6W7nXZwSsYMaRrWXhi+99jffLvExGsFDodYyNadOG7OQKsygzly5ESDoNIS19oD2U+hSkVeT6UTAFA=="}',
            ],
        ];
    }

    public static function decodeDataProvider(): array
    {
        return [
            'payment_register' => [
                'input'   => '{"ThreeDSessionId":"PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F","TransactionId":"2000000000032439","Code":0,"Message":"Ba\u015far\u0131l\u0131"}',
                'txType'  => PosInterface::TX_TYPE_PAY,
                'decoded' => [
                    'ThreeDSessionId' => 'PA49E341381C94587AB4CB196DAC10DC02E509578520E4471A3EEE2BB4830AE4F',
                    'TransactionId'   => '2000000000032439',
                    'Code'            => 0,
                    'Message'         => 'Başarılı',
                ],
            ],
        ];
    }
}
