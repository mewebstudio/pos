<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class PayFlexCPV4PosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder();

        $this->serializer = new Serializer([], [$encoder]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType): string
    {
        return \http_build_query($data);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        try {
            return $this->serializer->decode($data, XmlEncoder::FORMAT);
        } catch (NotEncodableValueException $notEncodableValueException) {
            if ($this->isHTML($data)) {
                // if something wrong server responds with HTML content
                throw new \RuntimeException($data, $notEncodableValueException->getCode(), $notEncodableValueException);
            }
        }

        throw $notEncodableValueException;
    }
}
