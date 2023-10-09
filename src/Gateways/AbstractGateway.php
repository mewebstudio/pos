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
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\SerializerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use function in_array;

abstract class AbstractGateway implements PosInterface
{
    /** @var array{gateway_endpoints: array{payment_api: string, gateway_3d: string, gateway_3d_host?: string, query_api?: string}} */
    protected $config;

    /** @var AbstractPosAccount */
    protected $account;

    /**
     * Processed Response Data
     *
     * @var array<string, mixed>|null
     */
    protected $response;

    /**
     * Raw Response Data From Bank
     *
     * @var array<string, mixed>|null
     */
    protected $data;

    /** @var HttpClient */
    protected $client;

    /** @var RequestDataMapperInterface */
    protected $requestDataMapper;

    /** @var ResponseDataMapperInterface */
    protected $responseDataMapper;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var LoggerInterface */
    protected $logger;

    /** @var bool */
    private $testMode = false;

    /**
     * @param array{gateway_endpoints: array{payment_api: string, gateway_3d: string, gateway_3d_host?: string, query_api?: string}} $config
     */
    public function __construct(
        array                          $config,
        AbstractPosAccount             $account,
        RequestDataMapperInterface     $requestDataMapper,
        ResponseDataMapperInterface    $responseDataMapper,
        SerializerInterface            $serializer,
        EventDispatcherInterface       $eventDispatcher,
        HttpClient                     $client,
        LoggerInterface                $logger
    )
    {
        $this->requestDataMapper  = $requestDataMapper;
        $this->responseDataMapper = $responseDataMapper;
        $this->serializer         = $serializer;
        $this->eventDispatcher    = $eventDispatcher;

        $this->config  = $config;
        $this->account = $account;
        $this->client  = $client;
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
     * @return non-empty-array<string, string>
     */
    public function getCurrencies(): array
    {
        return $this->requestDataMapper->getCurrencyMappings();
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
     * @phpstan-param self::TX_* $txType
     *
     * @param string|null $txType
     *
     * @return string
     */
    public function getApiURL(string $txType = null): string
    {
        return $this->config['gateway_endpoints']['payment_api'];
    }

    /**
     * @return string
     */
    public function get3DGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d'];
    }

    /**
     * @return string
     */
    public function get3DHostGatewayURL(): string
    {
        return $this->config['gateway_endpoints']['gateway_3d_host'] ?? $this->get3DGatewayURL();
    }

    /**
     * @return string
     */
    public function getQueryAPIUrl(): string
    {
        return $this->config['gateway_endpoints']['query_api'] ?? $this->getApiURL();
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
    public function payment(string $paymentModel, array $order, string $txType, ?AbstractCreditCard $card = null): PosInterface
    {
        $request = Request::createFromGlobals();

        $this->logger->debug('payment called', [
            'card_provided' => (bool) $card,
            'tx_type'       => $txType,
            'model'         => $paymentModel,
        ]);
        if (PosInterface::TX_POST_PAY === $txType) {
            $this->makeRegularPostPayment($order);

            return $this;
        }

        if (PosInterface::MODEL_NON_SECURE === $paymentModel) {
            if (!$card instanceof AbstractCreditCard) {
                throw new LogicException('Bu işlem için kredi kartı bilgileri zorunlu!');
            }
            $this->makeRegularPayment($order, $card, $txType);
        } elseif (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            $this->make3DPayment($request, $order, $txType, $card);
        } elseif (PosInterface::MODEL_3D_PAY === $paymentModel || PosInterface::MODEL_3D_PAY_HOSTING === $paymentModel) {
            $this->make3DPayPayment($request);
        } elseif (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $this->make3DHostPayment($request);
        } else {
            $this->logger->error('unsupported payment model', ['model' => $paymentModel]);
            throw new UnsupportedPaymentModelException();
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, AbstractCreditCard $card, string $txType): PosInterface
    {
        $this->logger->debug('making payment', [
            'model'   => PosInterface::MODEL_NON_SECURE,
            'tx_type' => $txType,
        ]);
        if (in_array($txType, [PosInterface::TX_PAY, PosInterface::TX_PRE_PAY], true)) {
            $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $card);
        } else {
            throw new LogicException(sprintf('Invalid transaction type "%s" provided', $txType));
        }

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
        $this->eventDispatcher->dispatch($event);
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
        $bankResponse   = $this->send($contents, $txType);
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        $this->logger->debug('making payment', [
            'model'   => PosInterface::MODEL_NON_SECURE,
            'tx_type' => PosInterface::TX_POST_PAY,
        ]);

        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_POST_PAY);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $contents       = $this->serializer->encode($requestData, PosInterface::TX_POST_PAY);
        $bankResponse   = $this->send($contents, PosInterface::TX_POST_PAY);
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_REFUND);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $data           = $this->serializer->encode($requestData, PosInterface::TX_REFUND);
        $bankResponse   = $this->send($data, PosInterface::TX_REFUND);
        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): PosInterface
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_CANCEL);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $data           = $this->serializer->encode($requestData, PosInterface::TX_CANCEL);
        $bankResponse   = $this->send($data, PosInterface::TX_CANCEL);
        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_STATUS);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $data           = $this->serializer->encode($requestData, PosInterface::TX_STATUS);
        $bankResponse   = $this->send($data, PosInterface::TX_STATUS, $this->getQueryAPIUrl());
        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $meta, $meta);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_HISTORY);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $data           = $this->serializer->encode($requestData, PosInterface::TX_HISTORY);
        $bankResponse   = $this->send($data, PosInterface::TX_HISTORY);
        $this->response = $this->responseDataMapper->mapHistoryResponse($bankResponse);

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
        $this->logger->debug('switching mode', ['mode' => $this->getModeInWord()]);

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
     * @phpstan-param PosInterface::TX_* $txType
     *
     * @param array<string, mixed>|string $contents data to send
     * @param string                      $txType
     * @param string|null                 $url      URL address of the API
     *
     * @return array<string, mixed>
     */
    abstract protected function send($contents, string $txType, ?string $url = null): array;

    /**
     * return values are used as a key in config file
     * @return string
     */
    protected function getModeInWord(): string
    {
        return $this->isTestMode() ? 'test' : 'production';
    }
}
