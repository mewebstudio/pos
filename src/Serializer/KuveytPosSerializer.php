<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Serializer;

class KuveytPosSerializer implements SerializerInterface
{
    use SerializerUtilTrait;

    /** @var string[] */
    private array $nonPaymentTransactions = [
        PosInterface::TX_TYPE_REFUND,
        PosInterface::TX_TYPE_REFUND_PARTIAL,
        PosInterface::TX_TYPE_STATUS,
        PosInterface::TX_TYPE_CANCEL,
        PosInterface::TX_TYPE_CUSTOM_QUERY,
        PosInterface::TX_TYPE_HISTORY,
        PosInterface::TX_TYPE_ORDER_HISTORY,
    ];

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
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, string $txType, ?string $format = self::FORMAT_XML): EncodedData
    {
        if (\in_array($txType, $this->nonPaymentTransactions, true)) {
            throw new UnsupportedTransactionTypeException(
                \sprintf('Serialization of the transaction %s is not supported', $txType)
            );
        }

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
        if (\in_array($txType, $this->nonPaymentTransactions, true)) {
            return $this->serializer->decode($data, JsonEncoder::FORMAT);
        }

        return $this->serializer->decode($data, XmlEncoder::FORMAT);
    }
}
