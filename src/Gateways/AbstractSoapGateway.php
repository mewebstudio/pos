<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\Client\SoapClientInterface;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractSoapGateway extends AbstractGateway
{
    protected SoapClientInterface $client;

    /**
     * @param array{gateway_endpoints: array{payment_api: non-empty-string, gateway_3d: non-empty-string, gateway_3d_host?: non-empty-string, query_api?: non-empty-string}} $config
     */
    public function __construct(
        array                       $config,
        AbstractPosAccount          $posAccount,
        RequestValueMapperInterface $valueMapper,
        RequestDataMapperInterface  $requestDataMapper,
        ResponseDataMapperInterface $responseDataMapper,
        EventDispatcherInterface    $eventDispatcher,
        SoapClientInterface         $soapClient,
        LoggerInterface             $logger
    ) {
        $this->client = $soapClient;
        parent::__construct(
            $config,
            $posAccount,
            $valueMapper,
            $requestDataMapper,
            $responseDataMapper,
            $eventDispatcher,
            $logger
        );
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface
    {
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $this->logger->debug('making payment', [
            'model'   => $paymentModel,
            'tx_type' => $txType,
        ]);
        if (!\in_array($txType, [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH], true)) {
            throw new LogicException(\sprintf('Invalid transaction type "%s" provided', $txType));
        }

        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $creditCard);

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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_PAY_POST_AUTH;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $this->logger->debug('making payment', [
            'model'   => $paymentModel,
            'tx_type' => $txType,
        ]);

        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_REFUND;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );

        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_CANCEL;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createCancelRequestData($this->account, $order);

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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_STATUS;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createStatusRequestData($this->account, $order);

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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );

        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $data,
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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $data,
        );
        $this->response = $this->responseDataMapper->mapHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        $txType       = PosInterface::TX_TYPE_ORDER_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

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

        $bankResponse = $this->client->call(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );
        $this->response = $this->responseDataMapper->mapOrderHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, string $apiUrl = null): PosInterface
    {
        $txType             = PosInterface::TX_TYPE_CUSTOM_QUERY;
        $paymentModel       = PosInterface::MODEL_NON_SECURE;
        $updatedRequestData = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        $event = new RequestDataPreparedEvent(
            $updatedRequestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $requestData,
            $paymentModel
        );

        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($updatedRequestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $updatedRequestData = $event->getRequestData();
        }

        $this->response = $this->client->call(
            $txType,
            $paymentModel,
            $updatedRequestData,
            [],
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setTestMode(bool $testMode): PosInterface
    {
        $this->client->setTestMode($testMode);

        return parent::setTestMode($testMode);
    }
}
