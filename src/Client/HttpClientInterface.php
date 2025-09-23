<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

interface HttpClientInterface
{
    /**
     * @param string                                               $path
     * @param array{body: string, headers?: array<string, string>} $payload
     *
     * @return ResponseInterface
     *
     * @throws ClientExceptionInterface
     */
    public function post(string $path, array $payload): ResponseInterface;
}
