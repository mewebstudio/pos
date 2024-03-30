<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractRequestDataMapper
 */
abstract class AbstractRequestDataMapper implements RequestDataMapperInterface
{
    protected EventDispatcherInterface $eventDispatcher;

    /** @var array<PosInterface::MODEL_*, string> */
    protected array $secureTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array<PosInterface::TX_TYPE_*, string>
     */
    protected array $txTypeMappings = [];

    /** @var array<CreditCardInterface::CARD_TYPE_*, string> */
    protected array $cardTypeMapping = [];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr',
        PosInterface::LANG_EN => 'en',
    ];

    /**
     * default olarak ISO 4217 kodlar tanimliyoruz.
     * fakat bazi banklar ISO standarti kullanmiyorlar.
     * Currency mapping
     *
     * @var non-empty-array<PosInterface::CURRENCY_*, string>
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '949',
        PosInterface::CURRENCY_USD => '840',
        PosInterface::CURRENCY_EUR => '978',
        PosInterface::CURRENCY_GBP => '826',
        PosInterface::CURRENCY_JPY => '392',
        PosInterface::CURRENCY_RUB => '643',
    ];

    /**
     * period mapping for recurring orders
     * @var array<'DAY'|'WEEK'|'MONTH'|'YEAR', string>
     */
    protected array $recurringOrderFrequencyMapping = [];

    protected bool $testMode = false;

    protected CryptInterface $crypt;

    /**
     * @param EventDispatcherInterface                $eventDispatcher
     * @param CryptInterface                          $crypt
     * @param array<PosInterface::CURRENCY_*, string> $currencyMappings
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, CryptInterface $crypt, array $currencyMappings = [])
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->crypt           = $crypt;
        if ([] !== $currencyMappings) {
            $this->currencyMappings = $currencyMappings;
        }
    }

    /**
     * @inheritDoc
     */
    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
    }

    /**
     * @inheritDoc
     */
    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    /**
     * @return array<CreditCardInterface::CARD_TYPE_*, string>
     */
    public function getCardTypeMapping(): array
    {
        return $this->cardTypeMapping;
    }

    /**
     * @return array<PosInterface::MODEL_*, string>
     */
    public function getSecureTypeMappings(): array
    {
        return $this->secureTypeMappings;
    }

    /**
     * @return array<PosInterface::TX_TYPE_*, string>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @return non-empty-array<PosInterface::CURRENCY_*, string>
     */
    public function getCurrencyMappings(): array
    {
        return $this->currencyMappings;
    }


    /**
     * @inheritDoc
     */
    public function setTestMode(bool $testMode): void
    {
        $this->testMode = $testMode;
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param string $txType
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function mapTxType(string $txType): string
    {
        if (!$this->isSupportedTxType($txType)) {
            throw new UnsupportedTransactionTypeException();
        }

        return $this->txTypeMappings[$txType];
    }

    /**
     * @param string $txType
     *
     * @return bool
     */
    public function isSupportedTxType(string $txType): bool
    {
        return isset($this->txTypeMappings[$txType]);
    }

    /**
     * @return array<'DAY'|'WEEK'|'MONTH'|'YEAR', string>
     */
    public function getRecurringOrderFrequencyMapping(): array
    {
        return $this->recurringOrderFrequencyMapping;
    }

    /**
     * formats installment
     * @param int $installment
     *
     * @return string
     */
    abstract protected function mapInstallment(int $installment): string;

    /**
     * @phpstan-param PosInterface::CURRENCY_* $currency
     *
     * @param string $currency
     *
     * @return string currency code that is accepted by bank
     */
    protected function mapCurrency(string $currency): string
    {
        return $this->currencyMappings[$currency] ?? $currency;
    }

    /**
     * @param float $amount
     *
     * @return int|string|float
     */
    protected function formatAmount(float $amount)
    {
        return $amount;
    }

    /**
     * @param string $period
     *
     * @return string
     */
    protected function mapRecurringFrequency(string $period): string
    {
        return $this->recurringOrderFrequencyMapping[$period] ?? $period;
    }

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     *
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $order
     *
     * @return string
     */
    protected function getLang(AbstractPosAccount $posAccount, array $order): string
    {
        if (isset($order['lang'])) {
            return $this->langMappings[$order['lang']];
        }

        return $this->langMappings[$posAccount->getLang()];
    }

    /**
     * prepares order for payment request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function preparePaymentOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for TX_TYPE_PAY_POST_AUTH type request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for order status request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareStatusOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for cancel request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareCancelOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares order for refund request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareRefundOrder(array $order): array
    {
        return $order;
    }

    /**
     * prepares history request
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return $data;
    }

    /**
     * prepares order for order history request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return $order;
    }
}
