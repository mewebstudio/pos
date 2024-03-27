<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Tests\Unit;

use Mews\Pos\Client\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

trait HttpClientTestTrait
{
    private function prepareClient(
        HttpClient $httpClient,
        string $responseContent,
        string $apiUrl,
        array $requestData
    ): void {
        $responseMock = $this->prepareHttpResponse($responseContent);

        $httpClient->expects(self::once())
            ->method('post')
            ->with($apiUrl, $requestData)
            ->willReturn($responseMock);
    }

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

    private function prepareHttpResponse(string $responseContent): ResponseInterface
    {
        $responseMock = $this->createMock(ResponseInterface::class);
        $streamMock   = $this->createMock(StreamInterface::class);

        $streamMock->expects(self::once())
            ->method('getContents')
            ->willReturn($responseContent);
        $responseMock->expects(self::once())
            ->method('getBody')
            ->willReturn($streamMock);

        return $responseMock;
    }
}
