<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\PosNet;
use Mews\Pos\Gateways\PosNetV1Pos;
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
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @param ClientInterface         $client
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        ClientInterface             $client,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory,
        SerializerInterface         $serializer,
        RequestValueMapperInterface $requestValueMapper,
        LoggerInterface             $logger,
        array                       $config
    )
    {
        parent::__construct(
            $client,
            $requestFactory,
            $streamFactory,
            $serializer,
            $logger,
            $config
        );
        $this->requestValueMapper = $requestValueMapper;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException when transaction type is not provided
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return parent::getApiURL().'/'.$this->requestValueMapper->mapTxType($txType);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }


    /**
     * @inheritDoc
     */
    protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        $request->withHeader('Content-Type', 'application/json');
        $body = $this->streamFactory->createStream($content->getData());

        return $request->withBody($body);
    }
}
