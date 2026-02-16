<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\Param3DHostPos;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ParamPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass
            || Param3DHostPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, ?string $txType = null, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml')
            ->withBody($body);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponse(string $txType, ResponseInterface $response, array $order): void
    {
        $decodedData = $this->serializer->decode($response->getBody()->getContents(), $txType);
        if (isset($decodedData['soap:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response'    => $decodedData,
                'order'       => $order,
                'tx_type'     => $txType,
            ]);

            throw new \RuntimeException($decodedData['soap:Fault']['faultstring'] ?? 'Bankaya istek başarısız!');
        }
    }
}
