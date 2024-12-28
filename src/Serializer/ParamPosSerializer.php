<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\ParamPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class ParamPosSerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return $gatewayClass === ParamPos::class;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): array
    {
        return $data;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        dd($data);
        return $this->serializer->decode($data, JsonEncoder::FORMAT);
    }
}
