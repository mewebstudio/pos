<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class PosNetV1PosHttpClient extends AbstractHttpClient
{
    private RequestValueMapperInterface $requestValueMapper;

    /**
     * @param non-empty-string        $apiBaseUrl
     * @param ClientInterface         $psrClient
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        string                      $apiBaseUrl,
        ClientInterface             $psrClient,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory,
        SerializerInterface         $serializer,
        LoggerInterface             $logger,
        RequestValueMapperInterface $requestValueMapper
    ) {
        parent::__construct(
            $apiBaseUrl,
            $psrClient,
            $requestFactory,
            $streamFactory,
            $serializer,
            $logger,
        );
        $this->requestValueMapper = $requestValueMapper;
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass, string $apiName): bool
    {
        return PosNetV1Pos::class === $gatewayClass && HttpClientInterface::API_NAME_PAYMENT_API === $apiName;
    }

    /**
     * @inheritDoc
     */
    public function supportsTx(string $txType, string $paymentModel, ?string $orderTxType = null): bool
    {
        try {
            $this->getRequestURIByTransactionType($txType);
        } catch (UnsupportedTransactionTypeException $e) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException when a transaction type is not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return $this->baseApiUrl.'/'.$this->getRequestURIByTransactionType($txType);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }


    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType): string
    {
        return $this->requestValueMapper->mapTxType($txType);
    }
}
