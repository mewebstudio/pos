<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

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
        return $gatewayClass === ParamPos::class;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): string
    {
        $data['@xmlns:xsi']  = 'http://www.w3.org/2001/XMLSchema-instance';
        $data['@xmlns:xsd']  = 'http://www.w3.org/2001/XMLSchema';
        $data['@xmlns:soap'] = 'http://schemas.xmlsoap.org/soap/envelope/';

        return $this->serializer->encode($data, XmlEncoder::FORMAT);
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
