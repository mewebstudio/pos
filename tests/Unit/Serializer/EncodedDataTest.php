<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Serializer;

use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Mews\Pos\Serializer\EncodedData
 */
class EncodedDataTest extends TestCase
{
    public function testGetters(): void
    {
        $object = new EncodedData('abc', SerializerInterface::FORMAT_FORM);

        $this->assertSame('abc', $object->getData());
        $this->assertSame(SerializerInterface::FORMAT_FORM, $object->getFormat());
    }
}
