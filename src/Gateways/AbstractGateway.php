<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\Client\HttpClient;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractGateway implements PosInterface
{
    /** @var array{gateway_endpoints: array{payment_api: non-empty-string, gateway_3d: non-empty-string, gateway_3d_host?: non-empty-string, query_api?: non-empty-string}} */
    protected array $config;

    protected AbstractPosAccount $account;

    /**
     * Processed Response Data
     *
     * @var array<string, mixed>|null
     */
    protected ?array $response;

    /**
     * Raw Response Data From Bank
     *
     * @var array<string, mixed>|null
     */
    protected ?array $data;

    protected HttpClient $client;

    protected RequestDataMapperInterface $requestDataMapper;

    protected ResponseDataMapperInterface $responseDataMapper;

    protected SerializerInterface $serializer;

    protected EventDispatcherInterface $eventDispatcher;

    protected LoggerInterface $logger;

    /**
     * @var array<PosInterface::TX_TYPE_*, array<int, PosInterface::MODEL_*>|bool>
     */
    protected static array $supportedTransactions = [];

    private bool $testMode = false;

    /**
     * @param array{gateway_endpoints: array{payment_api: non-empty-string, gateway_3d: non-empty-string, gateway_3d_host?: non-empty-string, query_api?: non-empty-string}} $config
     */
    public function __construct(
        array                          $config,
        AbstractPosAccount             $posAccount,
        RequestDataMapperInterface     $requestDataMapper,
        ResponseDataMapperInterface    $responseDataMapper,
        SerializerInterface            $serializer,
        EventDispatcherInterface       $eventDispatcher,
        HttpClient                     $httpClient,
        LoggerInterface                $logger
    )
    {
        $this->requestDataMapper  = $requestDataMapper;
        $this->responseDataMapper = $responseDataMapper;
        $this->serializer         = $serializer;
        $this->eventDispatcher    = $eventDispatcher;

        $this->config  = $config;
        $this->account = $posAccount;
        $this->client  = $httpClient;
        $this->logger  = $logger;
    }

    /**
     * @inheritdoc
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function getCurrencies(): array
    {
        return \array_keys($this->requestDataMapper->getCurrencyMappings());
    }

    /**
     * @return array{gateway_endpoints: array{payment_api: string, gateway_3d: string, gateway_3d_host?: string, query_api?: string}}
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return isset($this->response['status']) && $this->responseDataMapper::TX_APPROVED === $this->response['status'];
    }

    /**
     * @phpstan-param self::TX_TYPE_*     $txType
     * @phpstan-param self::MODEL_*       $paymentModel
     * @phpstan-param self::TX_TYPE_PAY_* $orderTxType
     *
     * @param string|null $txType
     * @param string|null $paymentModel
     * @param string|null $orderTxType
     *
     * @return non-empty-string
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        return $this->config['gateway_endpoints']['payment_api'];
    }

    /**
     * @return non-empty-string
     */
    public function get3DGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d'];
    }

    /**
     * @return non-empty-string
     */
    public function get3DHostGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d_host'] ?? $this->get3DGatewayURL();
    }

    /**
     * @phpstan-param self::TX_TYPE_* $txType
     * @phpstan-param self::TX_TYPE_PAY_* $orderTxType
     *
     * @param string|null $txType
     * @param string|null $orderTxType transaction type of order when it was made
     *
     * @return non-empty-string
     */
    public function getQueryAPIUrl(string $txType = null, ?string $orderTxType = null): string
    {
        return $this->config['gateway_endpoints']['query_api'] ?? $this->getApiURL(
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $orderTxType
        );
    }

    /**
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @inheritDoc
     */
    public function payment(string $paymentModel, array $order, string $txType, ?CreditCardInterface $creditCard = null): PosInterface
    {
        $request = Request::createFromGlobals();

        $this->logger->debug('payment called', [
            'card_provided' => (bool) $creditCard,
            'tx_type'       => $txType,
            'model'         => $paymentModel,
        ]);
        if (PosInterface::TX_TYPE_PAY_POST_AUTH === $txType) {
            $this->makeRegularPostPayment($order);

            return $this;
        }

        if (PosInterface::MODEL_NON_SECURE === $paymentModel) {
            if (!$creditCard instanceof CreditCardInterface) {
                throw new LogicException('Bu işlem için kredi kartı bilgileri zorunlu!');
            }

            $this->makeRegularPayment($order, $creditCard, $txType);
        } elseif (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            $this->make3DPayment($request, $order, $txType, $creditCard);
        } elseif (PosInterface::MODEL_3D_PAY === $paymentModel || PosInterface::MODEL_3D_PAY_HOSTING === $paymentModel) {
            $this->make3DPayPayment($request, $order, $txType);
        } elseif (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $this->make3DHostPayment($request, $order, $txType);
        } else {
            $this->logger->error('unsupported payment model', ['model' => $paymentModel]);
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface
    {
        $this->logger->debug('making payment', [
            'model'   => PosInterface::MODEL_NON_SECURE,
            'tx_type' => $txType,
        ]);
        if (\in_array($txType, [PosInterface::TX_TYPE_PAY_AUTH, PosInterface::TX_TYPE_PAY_PRE_AUTH], true)) {
            $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $creditCard);
        } else {
            throw new LogicException(\sprintf('Invalid transaction type "%s" provided', $txType));
        }

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

        $contents       = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $contents,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL($txType, PosInterface::MODEL_NON_SECURE)
        );
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        $txType = PosInterface::TX_TYPE_PAY_POST_AUTH;
        $this->logger->debug('making payment', [
            'model'   => PosInterface::MODEL_NON_SECURE,
            'tx_type' => $txType,
        ]);

        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

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

        $contents       = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $contents,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL($txType, PosInterface::MODEL_NON_SECURE)
        );
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        $txType      = PosInterface::TX_TYPE_REFUND;
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

        $data           = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $data,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $order['transaction_type'] ?? null
            )
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

        $data           = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $data,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL(
                $txType,
                PosInterface::MODEL_NON_SECURE,
                $order['transaction_type'] ?? null
            )
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

        $data           = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $data,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getQueryAPIUrl($txType)
        );
        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        $txType      = PosInterface::TX_TYPE_HISTORY;
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $data);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $data,
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

        $encodedRequestData = $this->serializer->encode($requestData, $txType);
        $bankResponse       = $this->send(
            $encodedRequestData,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL($txType, PosInterface::MODEL_NON_SECURE)
        );
        $this->response     = $this->responseDataMapper->mapHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        $txType      = PosInterface::TX_TYPE_ORDER_HISTORY;
        $requestData = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

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

        $data           = $this->serializer->encode($requestData, $txType);
        $bankResponse   = $this->send(
            $data,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $this->getApiURL($txType, PosInterface::MODEL_NON_SECURE)
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
        $updatedRequestData = $this->requestDataMapper->createCustomQueryRequestData($this->account, $requestData);

        $event = new RequestDataPreparedEvent(
            $updatedRequestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $requestData,
            PosInterface::MODEL_NON_SECURE
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

        $data           = $this->serializer->encode($updatedRequestData, $txType);
        $apiUrl         = $apiUrl ?? $this->getQueryAPIUrl($txType);
        $this->response = $this->send(
            $data,
            $txType,
            PosInterface::MODEL_NON_SECURE,
            $apiUrl
        );

        return $this;
    }

    /**
     * @param bool $testMode
     *
     * @return $this
     */
    public function setTestMode(bool $testMode): PosInterface
    {
        $this->testMode = $testMode;
        $this->requestDataMapper->setTestMode($testMode);
        $this->logger->debug('switching mode', ['is_test_mode' => $this->isTestMode()]);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getCardTypeMapping(): array
    {
        return $this->requestDataMapper->getCardTypeMapping();
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        return [PosInterface::LANG_TR, PosInterface::LANG_EN];
    }

    /**
     * Send requests to bank APIs
     *
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_*   $paymentModel
     *
     * @param array<string, mixed>|string $contents data to send
     * @param string                      $txType
     * @param string                      $paymentModel
     * @param non-empty-string            $url      URL address of the API
     *
     * @return array<string, mixed>
     *
     * @throws ClientExceptionInterface
     */
    abstract protected function send($contents, string $txType, string $paymentModel, string $url): array;

    /**
     * @inheritDoc
     */
    public static function isSupportedTransaction(string $txType, string $paymentModel): bool
    {
        if (!isset(static::$supportedTransactions[$txType])) {
            return false;
        }

        if (\is_bool(static::$supportedTransactions[$txType])) {
            return static::$supportedTransactions[$txType];
        }

        return \in_array($paymentModel, static::$supportedTransactions[$txType], true);
    }

    /**
     * @param array<string, mixed> $responseData
     *
     * @return bool
     */
    protected function is3DAuthSuccess(array $responseData): bool
    {
        $mdStatus = $this->responseDataMapper->extractMdStatus($responseData);

        if ($this->responseDataMapper->is3dAuthSuccess($mdStatus)) {
            $this->logger->info('3d auth success', ['md_status' => $mdStatus]);

            return true;
        }

        $this->logger->error('3d auth fail', ['md_status' => $mdStatus]);

        return false;
    }
}
