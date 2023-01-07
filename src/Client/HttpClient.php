<?php

namespace Mews\Pos\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use function http_build_query;

/**
 * @phpstan-type PostPayload array{body?: array<string, string>, headers?: array<string, string>, form_params?: array<string, string>}
 * PSR18 HTTP Client wrapper
 */
class HttpClient
{
    /** @var ClientInterface */
    protected $client;

    /** @var RequestFactoryInterface */
    protected $requestFactory;

    /** @var StreamFactoryInterface */
    protected $streamFactory;

    /**
     * @param ClientInterface         $client
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        ClientInterface         $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory
    ) {
        $this->client         = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory  = $streamFactory;
    }

    /**
     * @param PostPayload|null $payload
     */
    public function post(string $path, ?array $payload = []): ResponseInterface
    {
        return $this->send('POST', $path, $payload);
    }

    /**
     * @param PostPayload|null $payload
     */
    private function send(string $method, string $path, ?array $payload = []): ResponseInterface
    {
        $request = $this->createRequest($method, $path, $payload);

        return $this->client->sendRequest($request);
    }

    /**
     * @param PostPayload|null $payload
     */
    private function createRequest(string $method, string $url, ?array $payload = []): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $url);

        if ('POST' == $method) {
            $body = null;
            if (isset($payload['form_params'])) {
                $request         = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
                $payload['body'] = http_build_query($payload['form_params']);
            }
            if (isset($payload['body'])) {
                $body = $this->streamFactory->createStream($payload['body']);
            }
            $request = $request->withBody($body);
        }


        if (isset($payload['headers'])) {
            foreach ($payload['headers'] as $key => $value) {
                $request = $request->withHeader($key, $value);
            }
        }

        return $request;
    }
}
