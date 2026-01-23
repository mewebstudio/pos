<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Serializer\XmlPrefixNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\XmlPrefixNormalizer
 */
class XmlPrefixNormalizerTest extends TestCase
{
    private XmlPrefixNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new XmlPrefixNormalizer();
    }

    public function testSupportsNormalization(): void
    {
        $this->assertTrue($this->normalizer->supportsNormalization([], 'xml', ['xml_prefix' => 'ser']));
        $this->assertFalse($this->normalizer->supportsNormalization([], 'json', ['xml_prefix' => 'ser']));
        $this->assertFalse($this->normalizer->supportsNormalization([], 'xml', []));
        $this->assertFalse($this->normalizer->supportsNormalization('not-an-array', 'xml', ['xml_prefix' => 'ser']));
    }

    public function testGetSupportedTypes(): void
    {
        $this->assertSame(['*' => true], $this->normalizer->getSupportedTypes('xml'));
        $this->assertSame(['*' => true], $this->normalizer->getSupportedTypes(null));
    }

    public function testNormalize(): void
    {
        $data = [
            'key1' => 'value1',
            'key2' => [
                'subKey1' => 'subValue1',
            ],
        ];
        $context = ['xml_prefix' => 'ser'];

        $expected = [
            'ser:key1' => 'value1',
            'ser:key2' => [
                'ser:subKey1' => 'subValue1',
            ],
        ];

        $this->assertSame($expected, $this->normalizer->normalize($data, 'xml', $context));
    }

    public function testNormalizeDeeplyNested(): void
    {
        $data = [
            'a' => [
                'b' => [
                    'c' => 'value'
                ]
            ]
        ];
        $context = ['xml_prefix' => 'pre'];

        $expected = [
            'pre:a' => [
                'pre:b' => [
                    'pre:c' => 'value'
                ]
            ]
        ];

        $this->assertSame($expected, $this->normalizer->normalize($data, 'xml', $context));
    }
}
