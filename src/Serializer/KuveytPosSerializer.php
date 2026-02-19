<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\KuveytPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class KuveytPosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'KuveytTurkVPosMessage',
            XmlEncoder::ENCODING       => 'ISO-8859-1',
        ]);

        $this->serializer = new Serializer([], [$encoder, new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return KuveytPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType, ?string $format = self::FORMAT_XML): EncodedData
    {
        $format ??= self::FORMAT_XML;

        return new EncodedData(
            $this->serializer->encode($data, $format),
            $format
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, string $txType): array
    {
        return $this->serializer->decode($data, XmlEncoder::FORMAT);
    }
}
