<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Psr\Log\LoggerInterface;

/**
 * Soap Client Wrapper
 */
abstract class AbstractSoapClient implements SoapClientInterface
{
    private LoggerInterface $logger;

    private bool $isTestMode = false;

    /**
     * @var array{
     *     payment_api: non-empty-string,
     *     query_api?: non-empty-string
     * } $config
     */
    private array $config;

    private RequestValueMapperInterface $valueMapper;

    /**
     * @param array{
     *     payment_api: non-empty-string,
     *     query_api?: non-empty-string
     * } $config
     */
    public function __construct(
        array                       $config,
        RequestValueMapperInterface $valueMapper,
        LoggerInterface             $logger
    ) {
        $this->config      = $config;
        $this->valueMapper = $valueMapper;
        $this->logger      = $logger;
    }

    /**
     * @return non-empty-string
     */
    public function getApiURL(): string
    {
        return $this->config['payment_api'];
    }

    /**
     * @inheritDoc
     */
    public function call(
        string $txType,
        string $paymentModel,
        array  $requestData,
        array  $order,
        string $soapAction = null,
        string $url = null,
        array  $options = []
    ): array {
        $url    ??= $this->getApiURL();
        $client = new \SoapClient($url, $this->getClientOptions());

        $soapAction ??= $this->valueMapper->mapTxType($txType, $paymentModel, $order);

        $this->logger->debug('sending request', [
            'url'     => $url,
            'options' => $options,
        ]);

        try {
            $result = $client->__soapCall(
                $soapAction,
                $this->prepareRequestData($requestData),
                $options
            );
        } catch (\SoapFault $soapFault) {
            $this->logger->error('soap error response', [
                'message'    => $soapFault->getMessage(),
                'error_code' => $soapFault->getCode(),
            ]);

            throw $soapFault;
        }

        if (null === $result) {
            $this->logger->error('Bankaya istek başarısız!', [
                'response' => $result,
            ]);

            throw new \RuntimeException('Bankaya istek başarısız!');
        }

        // SOAP response type is object, we need to transform it to array
        /** @var non-empty-string $encodedResult */
        $encodedResult = \json_encode($result, JSON_THROW_ON_ERROR);

        return \json_decode($encodedResult, true, 100, JSON_THROW_ON_ERROR);
    }

    /**
     * @inheritDoc
     */
    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }


    /**
     * @inheritDoc
     */
    public function setTestMode(bool $isTestMode): void
    {
        $this->isTestMode = $isTestMode;
    }

    /**
     * @param array<string, mixed> $requestData
     *
     * @return array<string, mixed>
     */
    abstract protected function prepareRequestData(array $requestData): array;

    /**
     * @return array<string, mixed>
     */
    private function getClientOptions(): array
    {
        $sslConfig = [
            'allow_self_signed' => true,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ];

        if ($this->isTestMode) {
            // disable ssl errors when try on local without ssl certificate
            $sslConfig = [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ];
        }

        return [
            'trace'          => true,
            'encoding'       => 'UTF-8',
            'stream_context' => \stream_context_create(['ssl' => $sslConfig]),
            'exceptions'     => true,
        ];
    }
}
