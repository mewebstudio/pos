<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Client\HttpClientInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class PayFlexV4PosSearchApiSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME             => 'SearchRequest',
            XmlEncoder::ENCODING                   => 'UTF-8',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [],
        ]);

        $this->serializer = new Serializer([], [$encoder]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return PayFlexV4Pos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType): EncodedData
    {
        if (!in_array($txType, [
            PosInterface::TX_TYPE_STATUS,
            PosInterface::TX_TYPE_HISTORY,
            PosInterface::TX_TYPE_ORDER_HISTORY,
        ], true)) {
            throw new UnsupportedTransactionTypeException(sprintf(
                'Unsupported transaction type for %s',
                self::class
            ));
        }

        $format = self::FORMAT_XML;

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
        try {
            return $this->serializer->decode($data, XmlEncoder::FORMAT);
        } catch (NotEncodableValueException $notEncodableValueException) {
            if ($this->isHTML($data)) {
                // if something wrong server responds with HTML content
                throw new \RuntimeException($data, $notEncodableValueException->getCode(), $notEncodableValueException);
            }

            throw $notEncodableValueException;
        }
    }
}
