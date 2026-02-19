<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

class EncodedData
{
    /**
     * @var SerializerInterface::FORMAT_*
     */
    private string $format;

    /**
     * @var string encoded Data
     */
    private string $data;

    /**
     * @param string                        $data
     * @param SerializerInterface::FORMAT_* $format
     */
    public function __construct(string $data, string $format)
    {
        $this->data   = $data;
        $this->format = $format;
    }

    /**
     * @return SerializerInterface::FORMAT_*
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
