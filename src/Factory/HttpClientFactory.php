<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Mews\Pos\Client\HttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class HttpClientFactory
{
    /**
     * @param ClientInterface|null         $client
     * @param RequestFactoryInterface|null $requestFactory
     * @param StreamFactoryInterface|null  $streamFactory
     *
     * @return HttpClient
     */
    public static function createHttpClient(ClientInterface $client = null, RequestFactoryInterface $requestFactory = null, StreamFactoryInterface $streamFactory = null): HttpClient
    {
        $client ??= Psr18ClientDiscovery::find();
        $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
        $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();

        return new HttpClient(
            $client,
            $requestFactory,
            $streamFactory
        );
    }

    /**
     * @return HttpClient
     */
    public static function createDefaultHttpClient(): HttpClient
    {
        return self::createHttpClient(
            Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory()
        );
    }
}
