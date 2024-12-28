<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit;

use Mews\Pos\Client\HttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

trait HttpClientTestTrait
{
    /**
     * @param HttpClient|MockObject $httpClient
     */
    private function prepareClient(
        HttpClient $httpClient,
        string $responseContent,
        string $apiUrl,
        array $requestData,
        ?int $statusCode = null
    ): void {
        $responseMock = $this->prepareHttpResponse($responseContent, $statusCode);

        $httpClient->expects(self::once())
            ->method('post')
            ->with($apiUrl, $requestData)
            ->willReturn($responseMock);
    }

    /**
     * @param HttpClient|MockObject $httpClient
     */
    private function prepareHttpClientRequestMulti(
        HttpClient $httpClient,
        array $responseContents,
        array $apiUrls,
        array $requestData
    ): void {
        $returnMap = [];

        foreach ($responseContents as $index => $item) {
            $returnMap[] = [
                $apiUrls[$index],
                $requestData[$index],
                $this->prepareHttpResponse($item),
            ];
        }

        $httpClient->expects(self::exactly(\count($returnMap)))
            ->method('post')
            ->willReturnMap($returnMap);
    }

    private function prepareHttpResponse(string $responseContent, ?int $statusCode = null): ResponseInterface
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock   = $this->createMock(StreamInterface::class);

        $streamMock->expects(self::once())
            ->method('getContents')
            ->willReturn($responseContent);
        $responseMock->expects(self::once())
            ->method('getBody')
            ->willReturn($streamMock);

        if (null !== $statusCode) {
            $responseMock->expects(self::atLeastOnce())
                ->method('getStatusCode')
                ->willReturn($statusCode);
        }

        return $responseMock;
    }
}
