<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

class PayFlexV4PosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    private Serializer $serializer;

    public function __construct()
    {
        $encoder = new XmlEncoder([
            XmlEncoder::ROOT_NODE_NAME             => 'VposRequest',
            XmlEncoder::ENCODING                   => 'UTF-8',
            XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [
                XML_PI_NODE,
            ],
        ]);

        $this->serializer = new Serializer([], [$encoder]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType): string
    {
        if (PosInterface::TX_TYPE_HISTORY === $txType || PosInterface::TX_TYPE_ORDER_HISTORY === $txType) {
            throw new UnsupportedTransactionTypeException(
                \sprintf('Serialization of the transaction %s is not supported', $txType)
            );
        }

        if (PosInterface::TX_TYPE_STATUS === $txType) {
            return $this->serializer->encode($data, XmlEncoder::FORMAT, [
                XmlEncoder::ROOT_NODE_NAME             => 'SearchRequest',
                XmlEncoder::ENCODING                   => 'UTF-8',
                XmlEncoder::ENCODER_IGNORED_NODE_TYPES => [],
            ]);
        }

        return $this->serializer->encode($data, XmlEncoder::FORMAT);
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
