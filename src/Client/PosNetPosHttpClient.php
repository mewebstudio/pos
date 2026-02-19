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
    public static function supports(string $gatewayClass, ?string $apiName = null): bool
    {
        return PosNet::class === $gatewayClass;
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
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream(
            \sprintf('xmldata=%s', $content->getData()),
        );
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($body);
    }
}
