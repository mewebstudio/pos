<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Serializer;

use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Serializer\InterPosSerializer;
use PHPUnit\Framework\TestCase;

class InterPosSerializerTest extends TestCase
{
    private InterPosSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serializer = new InterPosSerializer();
    }

    public function testSupports(): void
    {
        $supports = $this->serializer::supports(InterPos::class);

        $this->assertTrue($supports);
    }

    public function testEncode(): void
    {
        $data   = ['abc' => '1'];
        $result = $this->serializer->encode($data);

        $this->assertSame($data, $result);
    }
}
