<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class KuveytPos3DFormHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD === $txType;
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return KuveytPos::class === $gatewayClass && HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function request(
        string              $txType,
        string              $paymentModel,
        array               $requestData,
        array               $order,
        ?string             $url = null,
        ?AbstractPosAccount $account = null
    ): string {
        $content = $this->serializer->encode($requestData, $txType);

        return $this->doRequest(
            $txType,
            $paymentModel,
            $content,
            $order,
            $url,
            $account,
            false,
        );
    }

    /**
     * @return RequestInterface
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withBody($body);
    }
}
