<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class AkbankPosHttpClient extends AbstractHttpClient
{
    private CryptInterface $crypt;

    public function __construct(
        CryptInterface          $crypt,
        ClientInterface         $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        SerializerInterface     $serializer,
        LoggerInterface         $logger,
        array                   $config
    )
    {
        parent::__construct($client, $requestFactory, $streamFactory, $serializer, $logger, $config);
        $this->crypt = $crypt;
    }

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return AkbankPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * @throws \InvalidArgumentException when transaction type is not provided
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        if (!$account instanceof AbstractPosAccount) {
            throw new \InvalidArgumentException('Account is required to create request hash');
        }
        $request = $this->requestFactory->createRequest('POST', $url);

        $body    = $this->streamFactory->createStream($content->getData());
        $request = $request->withBody($body);

        $hash = $this->crypt->hashString($content->getData(), $account->getStoreKey());

        $request = $request->withHeader('Content-Type', 'application/json');

        return $request->withHeader('auth-hash', $hash);
    }

    protected function checkFailResponse(string $txType, $response): void
    {
        if ($response->getStatusCode() === 400) {
            $this->logger->error('api error', ['status_code' => $response->getStatusCode()]);

            // when the data is sent fails validation checks we get 400 error
            $data = $this->serializer->decode($response->getBody()->getContents(), $txType);
            throw new \RuntimeException($data['message'], $data['code']);
        }
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param string                          $txType
     *
     * @return string
     */
    private function getRequestURIByTransactionType(string $txType): string
    {
        $arr = [
            PosInterface::TX_TYPE_HISTORY => 'portal/report/transaction',
        ];

        return $arr[$txType] ?? 'transaction/process';
    }
}
