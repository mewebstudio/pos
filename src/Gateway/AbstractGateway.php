<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateway;

use LogicException;
use Mews\Pos\Client\HttpClientStrategyInterface;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Request\ValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractGateway implements PosInterface
{
    /**
     * Processed Response Data
     *
     * @var array<string, mixed>|null
     */
    protected ?array $response;

    /**
     * @var array<PosInterface::TX_TYPE_*, array<int, PosInterface::MODEL_*>|bool>
     */
    protected static array $supportedTransactions = [];

    /**
     * @var array<int, PosInterface::MODEL_3D_*>
     */
    protected static array $threeDPaymentModels = [
        PosInterface::MODEL_3D_SECURE,
        PosInterface::MODEL_3D_PAY,
        PosInterface::MODEL_3D_HOST,
        PosInterface::MODEL_3D_PAY_HOSTING,
    ];

    /**
     * @var array<int, PosInterface::MODEL_*>
     */
    protected static array $paymentModelsWithCard = [
        PosInterface::MODEL_NON_SECURE,
        PosInterface::MODEL_3D_SECURE,
        PosInterface::MODEL_3D_PAY,
    ];

    /**
     * Whether the test_mode gateway_config key actually changes the request payload for this gateway.
     * When false, test vs production is controlled solely by the endpoint URLs in config.
     */
    protected static bool $testModeAffectsRequests = false;

    private bool $testMode = false;

    /**
     * @param array{
     *      gateway_configs?: array{
     *           lang?: PosInterface::LANG_*,
     *           test_mode?: bool,
     *           disable_3d_hash_check?: bool
     *      },
     *      gateway_endpoints: array{
     *           gateway_3d: non-empty-string,
     *           gateway_3d_host?: non-empty-string
     *      }
     *  } $config
     */
    public function __construct(
        protected array                       $config,
        protected AbstractPosAccount          $account,
        protected RequestValueMapperInterface $valueMapper,
        protected RequestDataMapperInterface  $requestDataMapper,
        protected ResponseDataMapperInterface $responseDataMapper,
        protected CryptInterface              $crypt,
        protected EventDispatcherInterface    $eventDispatcher,
        protected HttpClientStrategyInterface $clientStrategy,
        protected LoggerInterface             $logger
    ) {
        if (isset($this->config['gateway_configs']['test_mode'])) {
            $this->setTestMode($this->config['gateway_configs']['test_mode']);
        }
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
        return \array_keys($this->valueMapper->getCurrencyMappings());
    }

    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
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
     * @param PosInterface::MODEL_3D_* $paymentModel
     *
     * @return non-empty-string
     */
    public function get3DGatewayURL(string $paymentModel = PosInterface::MODEL_3D_SECURE): string
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel && isset($this->config['gateway_endpoints']['gateway_3d_host'])) {
            return $this->config['gateway_endpoints']['gateway_3d_host'];
        }

        return $this->config['gateway_endpoints']['gateway_3d'];
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
    public function payment(string $paymentModel, array $order, string $txType, ?CreditCardInterface $creditCard = null, ?array $gatewayResponseData = null): array
    {
        $this->logger->debug('payment called', [
            'card_provided' => (bool) $creditCard,
            'tx_type'       => $txType,
            'model'         => $paymentModel,
        ]);
        if (PosInterface::TX_TYPE_PAY_POST_AUTH === $txType) {
            return $this->makeRegularPostPayment($order);
        }

        if (PosInterface::MODEL_NON_SECURE === $paymentModel) {
            if (!$creditCard instanceof CreditCardInterface) {
                throw new LogicException('Bu işlem için kredi kartı bilgileri zorunlu!');
            }

            return $this->makeRegularPayment($order, $creditCard, $txType);
        }

        if (null === $gatewayResponseData || [] === $gatewayResponseData) {
            throw new LogicException('3D tür ödeme modelleri için bankadan 3D otorizasyon yanıt verileri gereklidir!');
        }

        if (PosInterface::MODEL_3D_SECURE === $paymentModel) {
            return $this->make3DPayment($gatewayResponseData, $order, $txType, $creditCard);
        }

        if (PosInterface::MODEL_3D_PAY === $paymentModel || PosInterface::MODEL_3D_PAY_HOSTING === $paymentModel) {
            return $this->make3DPayPayment($gatewayResponseData, $order, $txType);
        }

        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return $this->make3DHostPayment($gatewayResponseData, $order, $txType);
        }

        $this->logger->error('unsupported payment model', ['model' => $paymentModel]);
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): array
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
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse   = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );
        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): array
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
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->mapPaymentResponse($bankResponse, $txType, $order);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): array
    {
        $txType       = PosInterface::TX_TYPE_REFUND;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        if (isset($order['order_amount']) && $order['amount'] < $order['order_amount']) {
            $txType = PosInterface::TX_TYPE_REFUND_PARTIAL;
        }

        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order, $txType);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse   = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account,
            $order['transaction_type'] ?? null
        );
        $this->response = $this->responseDataMapper->mapRefundResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): array
    {
        $txType       = PosInterface::TX_TYPE_CANCEL;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse   = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account,
            $order['transaction_type'] ?? null
        );
        $this->response = $this->responseDataMapper->mapCancelResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): array
    {
        $txType       = PosInterface::TX_TYPE_STATUS;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->mapStatusResponse($bankResponse);

        return $this->response;
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): array
    {
        $txType       = PosInterface::TX_TYPE_ORDER_HISTORY;
        $paymentModel = PosInterface::MODEL_NON_SECURE;
        $requestData  = $this->requestDataMapper->createOrderHistoryRequestData($this->account, $order);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $txType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'tx_type'      => $event->getTxType(),
                'bank_name'    => $event->getBankName(),
                'initial_data' => $requestData,
                'updated_data' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $bankResponse */
        $bankResponse = $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            null,
            $this->account
        );

        $this->response = $this->responseDataMapper->mapOrderHistoryResponse($bankResponse);

        return $this->response;
    }

    /**
     * {@inheritDoc}
     */
    public function getCardTypeMapping(): array
    {
        return $this->valueMapper->getCardTypeMappings();
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array
    {
        return \array_keys($this->valueMapper->getLangMappings());
    }

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
    protected function is3dAuthSuccess(array $responseData): bool
    {
        $mdStatus = $this->responseDataMapper->extractMdStatus($responseData);

        if ($this->responseDataMapper->is3dAuthSuccess($mdStatus)) {
            $this->logger->info('3d auth success', ['md_status' => $mdStatus]);

            return true;
        }

        $this->logger->error('3d auth fail', ['md_status' => $mdStatus]);

        return false;
    }

    /**
     * @param PosInterface::MODEL_3D_* $paymentModel
     * @param PosInterface::TX_TYPE_*  $txType
     * @param CreditCardInterface|null $card
     * @param bool                     $createWithoutCard
     *
     * @throws \LogicException when inputs are not valid
     */
    protected function check3DFormInputs(string $paymentModel, string $txType, ?CreditCardInterface $card = null, bool $createWithoutCard = false): void
    {
        $paymentModels = $this->getSupported3DPaymentModelsForPaymentTransaction($txType);
        if (!self::isSupportedTransaction($txType, $paymentModel)) {
            throw new \LogicException(\sprintf(
                '%s ödeme altyapıda [%s] işlem tipi [%s] ödeme model(ler) desteklemektedir. Sağlanan ödeme model: [%s].',
                static::class,
                $txType,
                \implode(', ', $paymentModels),
                $paymentModel
            ));
        }

        if (PosInterface::MODEL_3D_HOST === $paymentModel && $card instanceof CreditCardInterface) {
            throw new \LogicException(\sprintf(
                'Kart bilgileri ile form verisi oluşturmak icin [%s] ödeme modeli kullanmayınız! Yerine [%s] ödeme model(ler)ini kullanınız.',
                $paymentModel,
                \implode(', ', $this->getSupported3DPaymentModelsForPaymentTransaction($txType, true))
            ));
        }

        if ($createWithoutCard) {
            return;
        }

        if ((PosInterface::MODEL_3D_SECURE === $paymentModel || PosInterface::MODEL_3D_PAY === $paymentModel)
            && !$card instanceof \Mews\Pos\Model\Card\CreditCardInterface
        ) {
            throw new \LogicException('Bu ödeme modeli için kart bilgileri zorunlu!');
        }
    }

    /**
     * @return bool
     */
    protected function is3DHashCheckDisabled(): bool
    {
        return $this->config['gateway_configs']['disable_3d_hash_check'] ?? false;
    }

    /**
     * Enable/Disable test mode
     *
     * @param bool $testMode
     */
    private function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
        $this->requestDataMapper->setTestMode($testMode);
        $this->logger->debug('switching mode', ['is_test_mode' => $this->isTestMode()]);
        if (!static::$testModeAffectsRequests) {
            $this->logger->warning(
                'test_mode gateway ayarı bu gateway için istek verisini etkilemez; '
                . 'test ve production ortamı ayrımı config\'deki endpoint URL\'leri ile yapılır',
                ['gateway' => static::class]
            );
        }
    }

    /**
     * @return array<int, PosInterface::TX_TYPE_*>
     */
    private function getSupported3DTxTypes(): array
    {
        $threeDSupportedTxTypes = [];
        $txTypes                = [
            PosInterface::TX_TYPE_PAY_AUTH,
            PosInterface::TX_TYPE_PAY_PRE_AUTH,
        ];
        foreach ($txTypes as $txType) {
            foreach (self::$threeDPaymentModels as $paymentModel) {
                if (self::isSupportedTransaction($txType, $paymentModel)) {
                    $threeDSupportedTxTypes[] = $txType;
                }
            }
        }

        return \array_values(\array_unique($threeDSupportedTxTypes));
    }

    /**
     * @param PosInterface::TX_TYPE_* $txType
     *
     * @return array<int, PosInterface::MODEL_*>
     */
    private function getSupported3DPaymentModelsForPaymentTransaction(string $txType, ?bool $withCard = null): array
    {
        $supported3DPaymentTxs = $this->getSupported3DTxTypes();
        if (!\in_array($txType, $supported3DPaymentTxs, true)) {
            throw new \LogicException(\sprintf(
                'Hatalı işlem tipi! Desteklenen işlem tipleri: [%s].',
                \implode(', ', $supported3DPaymentTxs)
            ));
        }

        $supportedPaymentModels = [];
        if (\is_bool(static::$supportedTransactions[$txType]) && static::$supportedTransactions[$txType]) {
            $supportedPaymentModels = self::$threeDPaymentModels;
        }

        /** @var array<int, PosInterface::MODEL_3D_*> $supportedPaymentModels */
        $supportedPaymentModels = [] === $supportedPaymentModels ? static::$supportedTransactions[$txType] : $supportedPaymentModels;

        if (null === $withCard) {
            return $supportedPaymentModels;
        }

        if ($withCard) {
            return \array_intersect($supportedPaymentModels, self::$paymentModelsWithCard);
        }

        return \array_diff($supportedPaymentModels, self::$paymentModelsWithCard);
    }
}
