<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Gateways\KuveytPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class KuveytPosSoapApiSerializer implements SerializerInterface
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
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return KuveytPos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType): EncodedData
    {
        $format = self::FORMAT_XML;

        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($data, $format, ['xml_prefix' => 'ser']);

        $serializeData['soapenv:Body']   = $data;
        $serializeData['@xmlns:soapenv'] = 'http://schemas.xmlsoap.org/soap/envelope/';
        $serializeData['@xmlns:ser']     = 'http://boa.net/BOA.Integration.VirtualPos/Service';

        return new EncodedData(
            $this->serializer->serialize($serializeData, $format),
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
