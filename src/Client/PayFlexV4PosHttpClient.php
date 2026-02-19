<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Message\RequestInterface;

class PayFlexV4PosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return PayFlexV4Pos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, ?string $url = null, ?AbstractPosAccount $account = null, bool $encode = true, bool $decode = true)
    {
        if ($encode) {
            $content = $this->serializer->encode($requestData, $txType);
            $content = $this->serializer->encode(
                ['prmstr' => $content->getData()],
                $txType,
                SerializerInterface::FORMAT_FORM
            );
        } else {
            $content = $this->serializer->encode(
                $requestData,
                $txType,
                SerializerInterface::FORMAT_FORM
            );
        }

        $url ??= $this->getApiURL($txType, $paymentModel, $order['transaction_type'] ?? null);

        $request = $this->createRequest($url, $content, $txType, $account);

        $this->logger->debug('sending request', ['url' => $url]);

        $response = $this->psrClient->sendRequest($request);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        $this->checkFailResponse($txType, $response, $order);
        $response->getBody()->rewind();

        return $decode
            ? $this->serializer->decode($response->getBody()->getContents(), $txType)
            : $response->getBody()->getContents();
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
