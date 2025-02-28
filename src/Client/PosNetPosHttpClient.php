<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Message\RequestInterface;

class PosNetPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNet::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, string $url = null, AbstractPosAccount $account = null, bool $encode = true, bool $decode = true)
    {
        $content = $this->serializer->encode($requestData, $txType);
        $content = $this->serializer->encode(
            ['xmldata' => $content->getData()],
            $txType,
            SerializerInterface::FORMAT_FORM
        );

        $url ??= $this->getApiURL($txType, $paymentModel, $order['transaction_type'] ?? null);

        $request = $this->createRequest($txType, $url, $content);

        $this->logger->debug('sending request', ['url' => $url]);

        $response = $this->client->sendRequest($request);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        $this->checkFailResponse($txType, $response);
        $response->getBody()->rewind();

        return $decode
            ? $this->serializer->decode($response->getBody()->getContents(), $txType)
            : $response->getBody()->getContents();
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');
        $body = $this->streamFactory->createStream($content->getData());

        return $request->withBody($body);
    }
}
