<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Serializer\EncodedData;
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
    protected function createRequest(string $url, EncodedData $content, ?string $txType = null, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream(
            \sprintf('xmldata=%s', $content->getData()),
        );
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
