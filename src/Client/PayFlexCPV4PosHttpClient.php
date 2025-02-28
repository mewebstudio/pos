<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PayFlexCPV4Pos;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class PayFlexCPV4PosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PayFlexCPV4Pos::class === $gatewayClass;
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
