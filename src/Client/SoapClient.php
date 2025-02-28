<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Psr\Log\LoggerInterface;

/**
 */
class SoapClient
{
    private LoggerInterface $logger;
    private bool $isTestMode;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function request(string $txType, string $soapAction, string $url, array $parameters)
    {
        $this->logger->debug('sending soap request', [
            'txType' => $txType,
            'url'    => $url,
        ]);

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

        $options = [
            'trace'          => true,
            'encoding'       => 'UTF-8',
            'stream_context' => stream_context_create(['ssl' => $sslConfig]),
            'exceptions'     => true,
        ];


        $client = new \SoapClient($url, $options);
        try {
            $result = $client->__soapCall(
                $soapAction,
                ['parameters' => $parameters]
            );
        } catch (\SoapFault $soapFault) {
            $this->logger->error('soap error response', [
                'message' => $soapFault->getMessage(),
            ]);

            throw $soapFault;
        }

        if (null === $result) {
            $this->logger->error('Bankaya istek başarısız!', [
                'response' => $result,
            ]);
            throw new \RuntimeException('Bankaya istek başarısız!');
        }

        return $result;
    }

    public function isTestMode(): bool
    {
        return $this->isTestMode;
    }

    public function setTestMode(bool $isTestMode): void
    {
        $this->isTestMode = $isTestMode;
    }
}
