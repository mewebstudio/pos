<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestValueMapper\RequestValueMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractGateway implements PosInterface
{
    /**
     * @var array{
     *     gateway_configs?: array{
     *          test_mode?: bool,
     *          disable_3d_hash_check?: bool
     *     },
     *     gateway_endpoints: array{
     *          payment_api: non-empty-string,
     *          payment_api_2?: non-empty-string,
     *          gateway_3d: non-empty-string,
     *          gateway_3d_host?: non-empty-string,
     *          query_api?: non-empty-string
     *     }
     * }
     */
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

    protected RequestValueMapperInterface $valueMapper;

    protected RequestDataMapperInterface $requestDataMapper;

    protected ResponseDataMapperInterface $responseDataMapper;

    protected EventDispatcherInterface $eventDispatcher;

    protected LoggerInterface $logger;

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

    private bool $testMode = false;

    /**
     * @param array{
     *      gateway_configs?: array{
     *           test_mode?: bool,
     *           disable_3d_hash_check?: bool
     *      },
     *      gateway_endpoints: array{
     *           payment_api: non-empty-string,
     *           payment_api_2?: non-empty-string,
     *           gateway_3d: non-empty-string,
     *           gateway_3d_host?: non-empty-string,
     *           query_api?: non-empty-string
     *      }
     *  } $config
     */
    public function __construct(
        array                          $config,
        AbstractPosAccount             $posAccount,
        RequestValueMapperInterface    $valueMapper,
        RequestDataMapperInterface     $requestDataMapper,
        ResponseDataMapperInterface    $responseDataMapper,
        EventDispatcherInterface       $eventDispatcher,
        LoggerInterface                $logger
    ) {
        $this->valueMapper        = $valueMapper;
        $this->requestDataMapper  = $requestDataMapper;
        $this->responseDataMapper = $responseDataMapper;
        $this->eventDispatcher    = $eventDispatcher;

        $this->config  = $config;
        $this->account = $posAccount;
        $this->logger  = $logger;

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

    /**
     * @return array{
     *      gateway_configs?: array{
     *          test_mode?: bool,
     *          disable_3d_hash_check?: bool
     *      },
     *      gateway_endpoints: array{
     *          payment_api: non-empty-string,
     *          payment_api_2?: non-empty-string,
     *          gateway_3d: non-empty-string,
     *          gateway_3d_host?: non-empty-string,
     *          query_api?: non-empty-string
     *      }
     * }
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
     * @phpstan-param self::TX_TYPE_*     $txType
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

    /**
     * @param PosInterface::MODEL_3D_* $paymentModel
     * @param PosInterface::TX_TYPE_*  $txType
     * @param CreditCardInterface|null $card
     * @param bool                     $createWithoutCard
     *
     * @throws \LogicException when inputs are not valid
     */
    protected function check3DFormInputs(string $paymentModel, string $txType, CreditCardInterface $card = null, bool $createWithoutCard = false): void
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
            && !$card instanceof \Mews\Pos\Entity\Card\CreditCardInterface
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
     * @return array<int, PosInterface::TX_TYPE_*>
     */
    private function getSupport3DTxTypes(): array
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
        $supported3DPaymentTxs = $this->getSupport3DTxTypes();
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
