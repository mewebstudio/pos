<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;

interface SerializerInterface
{
    public const FORMAT_XML = XmlEncoder::FORMAT;

    public const FORMAT_JSON = JsonEncoder::FORMAT;

    public const FORMAT_FORM = 'form';

    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param array<string, mixed>          $data
     * @param string                        $txType
     * @param SerializerInterface::FORMAT_* $format encoding format
     *
     * @return EncodedData
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function encode(array $data, string $txType, ?string $format = null): EncodedData;

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param string $data response received from the bank
     * @param string $txType
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException
     */
    public function decode(string $data, string $txType): array;
}
