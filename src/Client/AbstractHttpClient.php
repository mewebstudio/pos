<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * PSR18 HTTP Client wrapper
 */
abstract class AbstractHttpClient implements HttpClientInterface
{
    protected ClientInterface $client;

    protected RequestFactoryInterface $requestFactory;

    protected StreamFactoryInterface $streamFactory;
    protected SerializerInterface $serializer;
    protected array $config;
    protected bool $isTestMode;
    protected LoggerInterface $logger;

    /**
     * @param ClientInterface         $client
     * @param RequestFactoryInterface $requestFactory
     * @param StreamFactoryInterface  $streamFactory
     */
    public function __construct(
        ClientInterface         $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        SerializerInterface     $serializer,
        LoggerInterface         $logger,
        array                   $config
    )
    {
        $this->client         = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory  = $streamFactory;
        $this->config         = $config;
        $this->serializer     = $serializer;
        $this->logger         = $logger;
    }

    /**
     * todo protected?
     * @phpstan-param self::TX_TYPE_*     $txType
     * @phpstan-param self::MODEL_*       $paymentModel
     * @phpstan-param self::TX_TYPE_PAY_* $orderTxType
     *
     * @param string|null                 $txType
     * @param string|null                 $paymentModel
     * @param string|null                 $orderTxType
     *
     * @return non-empty-string
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (isset($this->config['query_api']) && \in_array($txType, [
                PosInterface::TX_TYPE_STATUS,
                PosInterface::TX_TYPE_CUSTOM_QUERY,
            ], true)) {
            //todo
            return $this->config['query_api'];
        }

        return $this->config['payment_api'];
    }

    /**
     * @inheritDoc
     */
    public function request(string $txType, string $paymentModel, array $requestData, array $order, string $url = null, AbstractPosAccount $account = null, bool $encode = true, bool $decode = true)
    {
        $content = $encode ? $this->serializer->encode($requestData, $txType) : $requestData;

        $url ??= $this->getApiURL($txType, $paymentModel, $order['transaction_type'] ?? null);

        $request = $this->createRequest($txType, $url, $content, $account);

        $this->logger->debug('sending request', ['url' => $url]);

        $response = $this->client->sendRequest($request);

        if ($response->getStatusCode() === 204) {
            // todo toslapos
            $this->logger->warning('response from api is empty');

            return $this->data = [];
        }

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        $this->checkFailResponse($txType, $response);
        $response->getBody()->rewind();

        return $decode
            ? $this->serializer->decode($response->getBody()->getContents(), $txType)
            : $response->getBody()->getContents();
    }

    /**
     * @param bool $isTestMode
     *
     * @return void
     */
    public function setTestMode(bool $isTestMode): void
    {
        $this->isTestMode = $isTestMode;
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }

    /**
     * @param PosInterface::TX_TYPE_* $txType // todo do we need it?
     * @param string                  $url
     * @param EncodedData             $content
     * @param AbstractPosAccount      $account
     *
     * @return RequestInterface
     */
    abstract protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface;

    /**
     * To be overridden
     * @param PosInterface::TX_TYPE_* $txType
     * @param                         $response
     *
     * @throws \RuntimeException when request failed
     */
    protected function checkFailResponse(string $txType, $response): void
    {
    }
}
