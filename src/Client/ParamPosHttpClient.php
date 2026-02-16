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
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml')
            ->withBody($body);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponseData(string $txType, ResponseInterface $response, array $responseData, array $order): void
    {
        if (isset($responseData['soap:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response'    => $responseData,
                'order'       => $order,
                'tx_type'     => $txType,
            ]);

            throw new \RuntimeException($responseData['soap:Fault']['faultstring'] ?? 'Bankaya istek başarısız!');
        }
    }
}
