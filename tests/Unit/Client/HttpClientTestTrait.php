<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

trait HttpClientTestTrait
{
    /**
     * @param string                                    $requestBody
     * @param array<array{name: string, value: string}> $headers
     *
     * @return RequestInterface
     */
    private function prepareHttpRequest(string $requestBody, array $headers): RequestInterface
    {
        $requestStream = $this->createMock(StreamInterface::class);
        $request       = $this->createMock(RequestInterface::class);

        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with($requestBody)
            ->willReturn($requestStream);

        $request->expects($this->once())
            ->method('withBody')
            ->with($requestStream)
            ->willReturn($request);

        if (count($headers) === 1) {
            $request->expects(self::once())
                ->method('withHeader')
                ->with($headers[0]['name'], $headers[0]['value'])
                ->willReturn($request);
        } else {
            $willReturnMap = [];
            foreach ($headers as $header) {
                $willReturnMap[] = [
                    $header['name'],
                    $header['value'],
                    $request,
                ];
            }

            $request->expects(self::exactly(count($headers)))
                ->method('withHeader')
                ->willReturnMap($willReturnMap);
        }

        return $request;
    }

    private function prepareHttpResponse(string $responseContent, ?int $statusCode = null): ResponseInterface
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock   = $this->createMock(StreamInterface::class);

        if (204 !== $statusCode) {
            $streamMock->expects(self::atLeastOnce())
                ->method('getContents')
                ->willReturn($responseContent);
            $responseMock->expects(self::atLeastOnce())
                ->method('getBody')
                ->willReturn($streamMock);
        }


        if (null !== $statusCode) {
            $responseMock->expects(self::atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);
        }

        return $responseMock;
    }
}
