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
     * Main api.
     */
    public const API_NAME_PAYMENT_API = 'payment_api';

    /**
     * Generally used for status, history queries.
     * Only some gateways support this api.
     */
    public const API_NAME_QUERY_API = 'query_api';

    /**
     * If gateway generates 3D form data by making a request to Gateway API,
     * then this api should be used.
     */
    public const API_NAME_GATEWAY_3D_API = 'gateway_3d';

    /**
     * @param PosInterface::TX_TYPE_*      $txType
     * @param PosInterface::MODEL_*        $paymentModel
     * @param PosInterface::TX_TYPE_*|null $orderTxType
     *
     * @return bool
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool;

    /**
     * @param class-string<PosInterface> $gatewayClass
     * @param self::API_NAME_*           $apiName
     *
     * @return bool
     */
    public static function supports(string $gatewayClass, string $apiName): bool;

    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     * @param array<string, mixed>    $requestData
     * @param array<string, mixed>    $order
     * @param non-empty-string|null   $url
     * @param AbstractPosAccount|null $account
     *
     * @return array<string, mixed>|string
     *
     * @throws UnsupportedTransactionTypeException
     * @throws NotEncodableValueException
     * @throws ClientExceptionInterface
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, ?string $url = null, ?AbstractPosAccount $account = null);
}
