<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\PayFlexV4Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Message\RequestInterface;

class PayFlexV4Pos3DFormHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PayFlexV4Pos::class === $gatewayClass && HttpClientInterface::API_NAME_GATEWAY_3D_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        return $txType === PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
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
    ): array {
        $content = new EncodedData(
            \http_build_query($requestData),
            SerializerInterface::FORMAT_FORM
        );

        return $this->doRequest(
            $txType,
            $paymentModel,
            $content,
            $order,
            $url,
            $account
        );
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
