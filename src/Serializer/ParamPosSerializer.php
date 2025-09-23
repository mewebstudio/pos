<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class ParamPosSerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $encoder          = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'soap:Envelope',
            XmlEncoder::ENCODING       => 'utf-8',
        ]);
        $this->serializer = new Serializer([], [$encoder]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return $gatewayClass === ParamPos::class
            || $gatewayClass === Param3DHostPos::class;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null, ?string $format = self::FORMAT_XML): EncodedData
    {
        $data['@xmlns:xsi']  = 'http://www.w3.org/2001/XMLSchema-instance';
        $data['@xmlns:xsd']  = 'http://www.w3.org/2001/XMLSchema';
        $data['@xmlns:soap'] = 'http://schemas.xmlsoap.org/soap/envelope/';

        $format ??= self::FORMAT_XML;

        return new EncodedData(
            $this->serializer->encode($data, $format),
            $format
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        $result = $this->serializer->decode($data, XmlEncoder::FORMAT);

        return $result['soap:Body'];
    }
}
