<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;

class PayFlexV4PosSearchApiHttpClient extends PayFlexV4PosHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayFlexV4Pos::class === $gatewayClass && HttpClientInterface::API_NAME_QUERY_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return PosInterface::TX_TYPE_STATUS === $txType;
    }
}
