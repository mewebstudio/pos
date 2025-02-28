<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\KuveytPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class KuveytPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException when transaction type is not provided
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (\in_array(
            $txType,
            [
                PosInterface::TX_TYPE_REFUND,
                PosInterface::TX_TYPE_REFUND_PARTIAL,
                PosInterface::TX_TYPE_STATUS,
                PosInterface::TX_TYPE_CANCEL,
                PosInterface::TX_TYPE_CUSTOM_QUERY,
            ],
            true
        )) {
            return $this->config['query_api'];
        }

        if (null !== $txType && null !== $paymentModel) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType, $paymentModel);
        }

        throw new \InvalidArgumentException('Transaction type is required to generate API URL');
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_STATUS|PosInterface::TX_TYPE_REFUND|PosInterface::TX_TYPE_REFUND_PARTIAL|PosInterface::TX_TYPE_CANCEL|PosInterface::TX_TYPE_CUSTOM_QUERY $txType
     *
     * @param array<string, mixed> $contents
     * @param string               $txType
     * @param string               $url
     *
     * @return array<string, mixed>
     *
     * @throws \SoapFault
     * @throws \RuntimeException
     */
    public function soapRequest(array $contents, string $txType, string $url): array
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
                $contents['VPosMessage']['TransactionType'],
                ['parameters' => ['request' => $contents]]
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

    /**
     * @return RequestInterface
     */
    protected function createRequest(string $txType, string $url, EncodedData $content, AbstractPosAccount $account = null): RequestInterface
    {
        $request = $this->requestFactory->createRequest('POST', $url);

        $body = $this->streamFactory->createStream($content->getData());
        $request = $request->withBody($body);

        return $request->withHeader('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_*   $paymentModel
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel): string
    {
        $arr = [
            PosInterface::TX_TYPE_PAY_AUTH => [
                PosInterface::MODEL_NON_SECURE => 'Non3DPayGate',
                PosInterface::MODEL_3D_SECURE  => 'ThreeDModelProvisionGate',
            ],
        ];

        if (!isset($arr[$txType])) {
            throw new UnsupportedTransactionTypeException();
        }

        if (!isset($arr[$txType][$paymentModel])) {
            throw new UnsupportedTransactionTypeException();
        }

        return $arr[$txType][$paymentModel];
    }
}
