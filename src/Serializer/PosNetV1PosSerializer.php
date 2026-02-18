<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\PosNetV1Pos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class PosNetV1PosSerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null, ?string $format = self::FORMAT_JSON): EncodedData
    {
        $format ??= self::FORMAT_JSON;

        return new EncodedData(
            $this->serializer->encode($data, $format),
            $format,
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        return $this->serializer->decode($data, JsonEncoder::FORMAT);
    }
}
