<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\PosInterface;

interface HttpClientStrategyInterface
{
    /**
     * @param PosInterface::TX_TYPE_* $txType
     * @param PosInterface::MODEL_*   $paymentModel
     *
     * @return HttpClientInterface
     */
    public function getClient(string $txType, string $paymentModel): HttpClientInterface;
}
