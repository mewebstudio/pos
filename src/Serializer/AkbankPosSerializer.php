<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

class AkbankPosSerializer implements SerializerInterface
{
    private Serializer $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer([], [new JsonEncoder()]);
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function encode(array $data, ?string $txType = null): string
    {
        return $this->serializer->encode($data, JsonEncoder::FORMAT);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        if ('' === $data) {
            return [];
        }

        $decodedData = $this->serializer->decode($data, JsonEncoder::FORMAT);

        if (PosInterface::TX_TYPE_HISTORY === $txType && isset($decodedData['data'])) {
            $decompressedData    = $this->decompress($decodedData['data']);
            $decodedData['data'] = \json_decode($decompressedData, true);
        }

        return $decodedData;
    }

    /**
     * @param string $data
     *
     * @return string json string
     */
    private function decompress(string $data): string
    {
        $decodedData = \base64_decode($data);
        $gzipStream  = gzopen('data://application/octet-stream;base64,'.base64_encode($decodedData), 'rb');

        if (!$gzipStream) {
            return '';
        }

        $decompressedData = '';
        $i                = 0;
        while (!gzeof($gzipStream)) {
            ++$i;
            if ($i > 1000000) {
                throw new \RuntimeException('Invalid history data');
            }

            $decompressedData .= gzread($gzipStream, 1024);
        }

        gzclose($gzipStream);

        return $decompressedData;
    }
}
