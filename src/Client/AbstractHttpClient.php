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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * PSR18 HTTP Client wrapper
 */
abstract class AbstractHttpClient implements HttpClientInterface
{
    protected ClientInterface $psrClient;

    protected RequestFactoryInterface $requestFactory;

    protected StreamFactoryInterface $streamFactory;
    protected SerializerInterface $serializer;
    /**
     * @var array{
     *     payment_api: non-empty-string,
     *     payment_api_2?: non-empty-string,
     *     query_api?: non-empty-string
     * }
     */
    protected array $config;

    protected LoggerInterface $logger;

    /**
     * @param ClientInterface            $psrClient
     * @param RequestFactoryInterface    $requestFactory
     * @param StreamFactoryInterface     $streamFactory
     * @param SerializerInterface        $serializer
     * @param LoggerInterface            $logger
     * @param array{
     *     payment_api: non-empty-string,
     *     payment_api_2?: non-empty-string,
     *     query_api?: non-empty-string} $config
     */
    public function __construct(
        ClientInterface         $psrClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        SerializerInterface     $serializer,
        LoggerInterface         $logger,
        array                   $config
    ) {
        $this->psrClient      = $psrClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory  = $streamFactory;
        $this->config         = $config;
        $this->serializer     = $serializer;
        $this->logger         = $logger;
    }

    /**
     * @param PosInterface::TX_TYPE_*|null     $txType
     * @param PosInterface::MODEL_* |null      $paymentModel
     * @param PosInterface::TX_TYPE_PAY_*|null $orderTxType
     *
     * @return non-empty-string
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (isset($this->config['query_api']) && \in_array($txType, [
                PosInterface::TX_TYPE_STATUS,
                PosInterface::TX_TYPE_CUSTOM_QUERY,
            ], true)) {
            return $this->config['query_api'];
        }

        return $this->config['payment_api'];
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
        ?AbstractPosAccount $account = null,
        bool                $encode = true,
        bool                $decode = true
    ) {

        try {
            $url ??= $this->getApiURL($txType, $paymentModel, $order['transaction_type'] ?? null);
        } catch (\Exception $e) {
            $msg = \sprintf('%s işlemi için API URL oluşturulamadı! API URL sağlayıp deneyiniz.', $txType);
            $this->logger->error($msg, [
                'config'       => $this->config,
                'txType'       => $txType,
                'paymentModel' => $paymentModel,
                'orderTxType'  => $order['transaction_type'] ?? null,
                'exception'    => $e,
            ]);

            throw $e;
        }

        $content = $this->serializer->encode($requestData, $txType);

        $request = $this->createRequest($url, $content, $txType, $account);

        $this->logger->debug('sending request', ['url' => $url]);

        $response = $this->psrClient->sendRequest($request);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        if ($response->getStatusCode() === 204) {
            $this->logger->warning('Response from api is empty', [
                'url'         => $url,
                'tx_type'     => $txType,
                'status_code' => $response->getStatusCode(),
            ]);

            return [];
        }

        $this->checkFailResponse($txType, $response, $order);

        if ($decode) {
            try {
                $decodedData = $this->serializer->decode($response->getBody()->getContents(), $txType);
            } catch (NotEncodableValueException $notEncodableValueException) {
                $response->getBody()->rewind();
                $this->logger->error('parsing bank response failed', [
                    'status_code' => $response->getStatusCode(),
                    'response'    => $response->getBody()->getContents(),
                    'message'     => $notEncodableValueException->getMessage(),
                ]);

                throw $notEncodableValueException;
            }
            $this->checkFailResponseData($txType, $response, $decodedData, $order);

            return $decodedData;
        }

        return $response->getBody()->getContents();
    }

    /**
     * @param non-empty-string        $url
     * @param EncodedData             $content
     * @param PosInterface::TX_TYPE_* $txType
     * @param AbstractPosAccount|null $account
     *
     * @return RequestInterface
     */
    abstract protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface;

    /**
     * Checks API response before decoding it.
     *
     * @param PosInterface::TX_TYPE_* $txType
     * @param ResponseInterface       $response
     * @param array<string, mixed>    $order
     *
     * @throws \RuntimeException when request fails
     */
    protected function checkFailResponse(string $txType, ResponseInterface $response, array $order): void
    {
        if ($response->getStatusCode() >= 500) {
            $this->logger->error('Api request failed!', [
                'status_code' => $response->getStatusCode(),
                'response'    => $response->getBody()->getContents(),
                'tx_type'     => $txType,
                'order'       => $order,
            ]);
            throw new \RuntimeException('İstek Başarısız!', $response->getStatusCode());
        }
    }


    /**
     * Checks API response data after decoding it.
     *
     * @param PosInterface::TX_TYPE_* $txType
     * @param ResponseInterface       $response
     * @param array<string, mixed>    $responseData
     * @param array<string, mixed>    $order
     *
     * @throws \RuntimeException when response is not successful
     */
    protected function checkFailResponseData(string $txType, ResponseInterface $response, array $responseData, array $order): void
    {
    }
}
