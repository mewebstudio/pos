<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Serializer;

use Mews\Pos\Gateways\InterPos;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class InterPosSerializer implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    public function encode(array $data, ?string $txType = null): string
    {
        return \http_build_query($data);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $data, ?string $txType = null): array
    {
        //genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = \preg_split('/(;;;|;;)/', $data);
        if (false === $resultValues) {
            throw new NotEncodableValueException();
        }

        $result = [];
        foreach ($resultValues as $val) {
            [$key, $value] = \explode('=', $val);
            $result[$key] = $value;
        }

        return $result;
    }
}
