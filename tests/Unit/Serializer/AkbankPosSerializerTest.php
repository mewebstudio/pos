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

    public function testDecodeHistory(): void
    {
        $input = file_get_contents(__DIR__.'/../test_data/akbankpos/history/daily_history_raw.json');

        $actual = $this->serializer->decode($input, PosInterface::TX_TYPE_HISTORY);

        $this->assertSame(6, count($actual));
        $this->assertArrayHasKey('data', $actual);
        $this->assertIsArray($actual['data']);
        $this->assertCount(3, $actual['data']);
        $this->assertCount(525, $actual['data']['txnDetailList']);
    }

    public function testInvalidHistoryData(): void
    {
        $input = \json_encode([
            'data'            => 'H4sIAAAAAAAAAIWSTW7bMBCFr2JwWZG/ylq5zobo24S2G7RIMiCkka1UP0EFIWmMHyV5gy5Q3yvUrKdOICBciW8mW/43lBb5J/qK+thXVSAYsQIE5hwzNiaypiIWJAJExqNkQdXFbUtUbxFFbh0Y2u/sjnMswPGKCER1YxQphWJtKKCK0WUFkqxM/6N4f9jduPBHHhblIui9Si+3/bKrMl6q4EVYa6D9rGpWziq329XmIRzVvkKbWt/9sXP0/3f6XK+mIfqpmn98iM7UOf6Oxkmjm6up3ejLzdX30Y/hoPGF5fHMONrKmIWxUxNNO3DNy4Dd0jNSZRGGcVSGI0FUzk2BnJMc0NtzkmS5/22bec3p5yMadZrztUovIcUTFLFhZRjVNn2F2Qz67LrrkrAhXYpdcTMp+FQImUAE+vTzalBGj1GrbdhFifhC7wvoYLaD/ZMkqZScoojQyUWJElwkuscB2dCgsoM5/qQe+Wt79p+qXb/bN3rS/n60huvmq4OT0XJhITxaecc1OmfQxYjzLu0sAmEvwmtl3eBK+rgqSx7I7PjhA/iqfsW9s9FHYA0TZbwOySfHm8Mtz1e0J4u9TmY9gsumxbe5N3D7h/PW4rgDgMAAA==',
            'requestId'       => 'VPS00020599122225999999999920240425095907000052',
            'terminal'        => [
                'terminalSafeId' => '2023090417500284633D137A249DBBEB',
                'merchantSafeId' => '2023090417500272654BD9A49CF07574',
            ],
            'responseMessage' => 'SUCCESSFUL',
            'txnDateTime'     => '2024-04-25T09:59:06.582',
            'responseCode'    => 'VPS-0000',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->serializer->decode($input, PosInterface::TX_TYPE_HISTORY);
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
