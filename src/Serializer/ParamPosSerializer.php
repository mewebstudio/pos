<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\ParamPos;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class ParamPosSerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME => 'soap:Envelope',
            XmlEncoder::ENCODING       => 'utf-8',
        ]);
        $this->serializer = new Serializer([], [$encoder, new JsonEncoder()]);
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
    public function encode(array $data, ?string $txType = null) //todo
    {
        $data['@xmlns'] = 'https://turkpos.com.tr/';
        $wrapper = [
            '@xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
            '@xmlns:xsd' => 'http://www.w3.org/2001/XMLSchema',
            '@xmlns:soap' => 'http://schemas.xmlsoap.org/soap/envelope/',
            'soap:Body' => [
                $txType => $data,
            ],
        ];

        return $this->serializer->encode($wrapper, XmlEncoder::FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        // example fail response:
        // ^ array:1 [▼
        //  "soap:Fault" => array:3 [▼
        //    "faultcode" => "soap:Server"
        //    "faultstring" => "Server was unable to process request. ---> Object reference not set to an instance of an object."
        //    "detail" => ""
        //  ]
        //dd($data);
        $result = $this->serializer->decode($data, XmlEncoder::FORMAT);

        return $result['soap:Body'];
    }
}
