<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Psr\Log\LoggerInterface;

/**
 * Soap Client Wrapper
 */
class SoapClient implements SoapClientInterface
{
    private LoggerInterface $logger;

    private bool $isTestMode = false;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function call(string $url, string $soapAction, array $data, array $options = []): array
    {
        $client = new \SoapClient($url, $this->getClientOptions());
        try {
            $result = $client->__soapCall(
                $soapAction,
                $data,
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
     * @return array<string, mixed>
     */
    private function getClientOptions(): array
    {
        $sslConfig = [
            'allow_self_signed' => true,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ];

        if ($this->isTestMode) {
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
            'stream_context' => stream_context_create(['ssl' => $sslConfig]),
            'exceptions'     => true,
        ];
    }
}
