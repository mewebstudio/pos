<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class PayFlexCPV4Pos3DFormHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass && HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName;
    }

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
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);
        $body    = $this->streamFactory->createStream($content->getData());

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', 'text/xml')
            ->withBody($body);
    }
}
