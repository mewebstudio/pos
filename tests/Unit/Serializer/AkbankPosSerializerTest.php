<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Generator;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\AkbankPosSerializer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\AkbankPosSerializer
 */
class AkbankPosSerializerTest extends TestCase
{
    private AkbankPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new AkbankPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(AkbankPos::class);

        $this->assertTrue($supports);
    }


    /**
     * @dataProvider encodeDataProvider
     */
    public function testEncode(array $data, string $expected): void
    {
        $result   = $this->serializer->encode($data);
        $expected = str_replace(["\r"], '', $expected);

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

    public static function encodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => ['abc' => 1],
            'expected' => '{"abc":1}',
        ];
    }

    public static function decodeDataProvider(): Generator
    {
        yield 'test1' => [
            'input'    => '{"abc": 1}',
            'txType'   => PosInterface::TX_TYPE_PAY_AUTH,
            'expected' => ['abc' => 1],
        ];
    }
}
