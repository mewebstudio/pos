<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\ParamPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class ParamPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ParamPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            if (!isset($this->config['gateway_endpoints']['payment_api_2'])) {
                throw new \RuntimeException('3D Host ödemeyi kullanabilmek için "payment_api_2" endpointi tanımlanmalıdır.');
            }

            return $this->config['gateway_endpoints']['payment_api_2'];
        }

        return parent::getApiURL($txType, $paymentModel, $orderTxType);
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        $body    = $this->streamFactory->createStream($content->getData());
        $request = $request->withBody($body);

        return $request->withHeader('Content-Type', 'text/xml');
    }

    protected function checkFailResponse(string $txType, $response): void
    {
        $decodedData = $this->serializer->decode($response->getBody()->getContents(), $txType);
        if (isset($decodedData['soap:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response'    => $decodedData,
            ]);

            // todo replace with client exceptions?
            throw new \RuntimeException($decodedData['soap:Fault']['faultstring'] ?? 'Bankaya istek başarısız!');
        }
    }
}
