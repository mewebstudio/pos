<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\KuveytSoapApiPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class KuveytSoapApiPosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'soapenv:Envelope',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [
                XML_PI_NODE,
            ],
        ]);

        $this->serializer = new Serializer([new XmlPrefixNormalizer()], [$encoder, new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytSoapApiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType, ?string $format = self::FORMAT_XML): EncodedData
    {
        $format ??= self::FORMAT_XML;

        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($data, $format, ['xml_prefix' => 'ser']);

        $serializeData['soapenv:Body']   = $data;
        $serializeData['@xmlns:soapenv'] = 'http://schemas.xmlsoap.org/soap/envelope/';
        $serializeData['@xmlns:ser']     = 'http://boa.net/BOA.Integration.VirtualPos/Service';

        return new EncodedData(
            $this->serializer->serialize($serializeData, XmlEncoder::FORMAT),
            $format
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, string $txType): array
    {
        $decodedData = $this->serializer->decode($data, XmlEncoder::FORMAT);

        return $decodedData['s:Body'];
    }
}
