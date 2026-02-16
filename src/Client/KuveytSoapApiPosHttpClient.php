<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class KuveytSoapApiPosHttpClient extends AbstractHttpClient
{
    private RequestValueMapperInterface $requestValueMapper;

    /**
     * @param ClientInterface         $psrClient
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        ClientInterface             $psrClient,
        RequestFactoryInterface     $requestFactory,
        StreamFactoryInterface      $streamFactory,
        SerializerInterface         $serializer,
        LoggerInterface             $logger,
        array                       $config,
        RequestValueMapperInterface $requestValueMapper
    ) {
        parent::__construct(
            $psrClient,
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
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytSoapApiPos::class === $gatewayClass;
    }

    /**
     * @return RequestInterface
     */
    protected function createRequest(string $url, EncodedData $content, ?string $txType = null, ?AbstractPosAccount $account = null): RequestInterface
    {
        if (null === $txType) {
            throw new \InvalidArgumentException('Transaction type is required to generate API URL');
        }

        $body    = $this->streamFactory->createStream($content->getData());
        $request = $this->requestFactory->createRequest('POST', $url);

        $soapActionHeader = 'http://boa.net/BOA.Integration.VirtualPos/Service/IVirtualPosService/'.$this->requestValueMapper->mapTxType($txType);

        return $request->withHeader('Content-Type', 'text/xml; charset=UTF-8')
            ->withHeader('SOAPAction', $soapActionHeader)
            ->withBody($body);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponse(string $txType, ResponseInterface $response, array $order): void
    {
        $responseContent = $response->getBody()->getContents();
        if ('' === $responseContent) {
            $this->logger->error('Api request failed!', [
                'status_code' => $response->getStatusCode(),
                'tx_type'     => $txType,
                'order'       => $order,
            ]);

            throw new \RuntimeException('Bankaya istek başarısız!', $response->getStatusCode());
        }

        $decodedData = $this->serializer->decode($responseContent, $txType);

        if (isset($decodedData['s:Fault'])) {
            $this->logger->error('soap error response', [
                'status_code' => $response->getStatusCode(),
                'response'    => $decodedData,
                'tx_type'     => $txType,
                'order'       => $order,
            ]);

            throw new \RuntimeException($decodedData['s:Fault']['faultstring']['#'] ?? 'Bankaya istek başarısız!');
        }
    }
}
