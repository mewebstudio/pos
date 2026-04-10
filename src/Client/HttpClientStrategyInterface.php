<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\PosInterface;

interface HttpClientStrategyInterface
{
    /**
     * @return array<HttpClientInterface::API_NAME_*, HttpClientInterface>
     */
    public function getAllClients(): array;

    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     *
     * @return HttpClientInterface
     */
    public function getClient(string $txType, string $paymentModel): HttpClientInterface;
}
