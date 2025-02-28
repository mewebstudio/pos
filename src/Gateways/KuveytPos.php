<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;
use SoapClient;
use SoapFault;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kuveyt banki desteleyen Gateway
 */
class KuveytPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'KuveytPos';

    /** @var KuveytPosAccount */
    protected AbstractPosAccount $account;

    /** @var KuveytPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var KuveytPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_NON_SECURE,
            PosInterface::MODEL_3D_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => false,
        PosInterface::TX_TYPE_PAY_POST_AUTH  => false,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return KuveytPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true): string
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        $this->logger->debug('preparing 3D form data');

        return $this->getCommon3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            $creditCard
        );
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     *
     * @throws SoapFault
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $gatewayResponse = $request->request->get('AuthenticationResponse');
        if (!\is_string($gatewayResponse)) {
            throw new LogicException('AuthenticationResponse is missing');
        }

        $gatewayResponse = \urldecode($gatewayResponse);
        $gatewayResponse = $this->serializer->decode($gatewayResponse, $txType);

        if (!$this->is3DAuthSuccess($gatewayResponse)) {
            $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, null, $txType, $order);

            return $this;
        }

        $this->logger->debug('finishing payment');

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            PosInterface::MODEL_3D_SECURE
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse = $this->client2->request(
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $requestData,
        );

        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        $txType = PosInterface::TX_TYPE_REFUND;
        if (isset($order['order_amount']) && $order['amount'] < $order['order_amount']) {
            $txType = PosInterface::TX_TYPE_REFUND_PARTIAL;
        }

        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            PosInterface::MODEL_NON_SECURE
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse   = $this->sendSoapRequest(
            $requestData,
            $txType,
            $this->getApiURL(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $order['transaction_type'] ?? null
            ),
        );
        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): PosInterface
    {
        $txType      = PosInterface::TX_TYPE_CANCEL;
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            PosInterface::MODEL_NON_SECURE
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse   = $this->sendSoapRequest(
            $requestData,
            $txType,
            $this->getApiURL(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $order['transaction_type'] ?? null
            ),
        );
        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        $txType      = PosInterface::TX_TYPE_STATUS;
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            PosInterface::MODEL_NON_SECURE
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse   = $this->sendSoapRequest(
            $requestData,
            $txType,
            $this->getApiURL(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $order['transaction_type'] ?? null
            ),
        );

        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }


    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send(EncodedData $encodedData, string $txType, string $paymentModel, string $url): array
    {
        $this->logger->debug('sending request', ['url' => $url]);
        $body     = [
            'body'    => $encodedData->getData(),
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
        ];
        $response = $this->client->post($url, $body);
        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
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
     * @throws SoapFault
     * @throws RuntimeException
     */
    private function sendSoapRequest(array $contents, string $txType, string $url): array
    {
        $this->logger->debug('sending soap request', [
            'txType' => $txType,
            'url'    => $url,
        ]);

        $sslConfig = [
            'allow_self_signed' => true,
            'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ];
        if ($this->isTestMode()) {
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


        $client = new SoapClient($url, $options);
        try {
            $result = $client->__soapCall(
                $contents['VPosMessage']['TransactionType'],
                ['parameters' => ['request' => $contents]]
            );
        } catch (SoapFault $soapFault) {
            $this->logger->error('soap error response', [
                'message' => $soapFault->getMessage(),
            ]);

            throw $soapFault;
        }

        if (null === $result) {
            $this->logger->error('Bankaya istek başarısız!', [
                'response' => $result,
            ]);
            throw new RuntimeException('Bankaya istek başarısız!');
        }

        $encodedResult = \json_encode($result);

        if (false === $encodedResult) {
            return [];
        }

        return $this->serializer->decode($encodedResult, $txType);
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param KuveytPosAccount                     $kuveytPosAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param non-empty-string                     $gatewayURL
     * @param CreditCardInterface|null             $creditCard
     *
     * @return string HTML form
     *
     * @throws RuntimeException
     * @throws UnsupportedTransactionTypeException
     */
    private function getCommon3DFormData(KuveytPosAccount $kuveytPosAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): string
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $kuveytPosAccount,
            $order,
            $paymentModel,
            $txType,
            $creditCard
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        return $this->client2->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $gatewayURL,
            true,
            false
        );
    }
}
