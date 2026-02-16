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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class AkbankPosHttpClient extends AbstractHttpClient
{
    private CryptInterface $crypt;

    public function __construct(
        ClientInterface         $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface  $streamFactory,
        SerializerInterface     $serializer,
        LoggerInterface         $logger,
        array                   $config,
        CryptInterface          $crypt
    ) {
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
     * @throws \InvalidArgumentException when a transaction type is not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, ?string $txType = null, ?AbstractPosAccount $account = null): RequestInterface
    {
        if (!$account instanceof AbstractPosAccount) {
            throw new \InvalidArgumentException('Account is required to create request hash');
        }

        $body = $this->streamFactory->createStream($content->getData());
        $hash = $this->crypt->hashString($content->getData(), $account->getStoreKey());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/json')
            ->withHeader('auth-hash', $hash)
            ->withBody($body);
    }

    /**
     * @inheritDoc
     */
    protected function checkFailResponse(string $txType, ResponseInterface $response, array $order): void
    {
        if ($response->getStatusCode() >= 400) {
            $this->logger->error('api error', [
                'status_code' => $response->getStatusCode(),
                'order'       => $order,
                'tx_type'     => $txType,
                'response'   => $response->getBody()->getContents(),
            ]);

            $response->getBody()->rewind();
            // when the data is sent fails validation checks we get 400 error
            $data = $this->serializer->decode($response->getBody()->getContents(), $txType);
            throw new \RuntimeException($data['message'], $data['code']);
        }
    }

    /**
     * @param PosInterface::TX_TYPE_* $txType
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
