<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class GarantiPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        return $this->requestFactory->createRequest('POST', $url)
            ->withBody($body);
    }
}
