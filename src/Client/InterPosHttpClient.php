<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class InterPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return InterPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
