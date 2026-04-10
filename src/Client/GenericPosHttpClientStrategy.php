<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

class GenericPosHttpClientStrategy implements HttpClientStrategyInterface
{
    /**
     * @var array<HttpClientInterface>
     */
    private array $clients;

    /**
     * @param array<HttpClientInterface::API_NAME_*, HttpClientInterface> $clients
     */
    public function __construct(array $clients)
    {
        $this->clients = $clients;
    }

    /**
     * @inheritDoc
     */
    public function getAllClients(): array
    {
        return $this->clients;
    }

    /**
     * @inheritDoc
     */
    public function getClient(string $txType, string $paymentModel): HttpClientInterface
    {
        foreach ($this->clients as $client) {
            if ($client->supportsTx($txType, $paymentModel)) {
                return $client;
            }
        }

        throw new \InvalidArgumentException("No HTTP client configured for transaction type: $txType");
    }
}
