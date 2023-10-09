<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * AbstractRequestDataMapper
 */
abstract class AbstractRequestDataMapper implements RequestDataMapperInterface
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var array<PosInterface::MODEL_*, string> */
    protected $secureTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array<PosInterface::TX_*, string>
     */
    protected $txTypeMappings = [];

    /** @var array<AbstractCreditCard::CARD_TYPE_*, string> */
    protected $cardTypeMapping = [];

    /** @var array<PosInterface::LANG_*, string> */
    protected $langMappings = [
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
    protected $currencyMappings = [
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
    protected $recurringOrderFrequencyMapping = [];

    /** @var bool */
    protected $testMode = false;

    /** @var CryptInterface */
    protected $crypt;

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
     * @param string $period
     *
     * @return string
     */
    public function mapRecurringFrequency(string $period): string
    {
        return $this->recurringOrderFrequencyMapping[$period] ?? $period;
    }

    /**
     * @return array<AbstractCreditCard::CARD_TYPE_*, string>
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
     * @return array<PosInterface::TX_*, string>
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
     * @phpstan-param PosInterface::CURRENCY_* $currency
     *
     * @param string $currency
     *
     * @return string currency code that is accepted by bank
     */
    public function mapCurrency(string $currency): string
    {
        return $this->currencyMappings[$currency] ?? $currency;
    }

    /**
     * @phpstan-param PosInterface::TX_* $txType
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

    abstract public function mapInstallment(?int $installment): string;

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     *
     * @param AbstractPosAccount   $account
     * @param array<string, mixed> $order
     *
     * @return string
     */
    protected function getLang(AbstractPosAccount $account, array $order): string
    {
        if (isset($order['lang'])) {
            return $this->langMappings[$order['lang']];
        }

        return $this->langMappings[$account->getLang()];
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
     * prepares order for TX_POST_PAY type request
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
     * prepares order for history request
     *
     * @param array<string, mixed> $order
     *
     * @return array<string, mixed>
     */
    protected function prepareHistoryOrder(array $order): array
    {
        return $order;
    }
}
