<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

interface HttpClientInterface
{
    /**
     * @param class-string<PosInterface> $gatewayClass
     *
     * @return bool
     */
    public static function supports(string $gatewayClass): bool;

    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     * @param array<string, mixed>    $requestData
     * @param array<string, mixed>    $order
     * @param non-empty-string|null   $url
     * @param AbstractPosAccount|null $account
     * @param bool                    $encode
     * @param bool                    $decode
     *
     * @return ($decode is true ? array<string, mixed> : string)
     *
     * @throws UnsupportedTransactionTypeException
     * @throws NotEncodableValueException
     * @throws ClientExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, string $url = null, AbstractPosAccount $account = null, bool $encode = true, bool $decode = true);
}
