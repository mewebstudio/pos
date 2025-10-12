<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestValueMapper;

use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

abstract class AbstractRequestValueMapper implements RequestValueMapperInterface
{
    /** @var array<CreditCardInterface::CARD_TYPE_*, string> */
    protected array $cardTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>>
     */
    protected array $txTypeMappings = [];

    /**
     * period mapping for recurring orders
     * @var array<'DAY'|'WEEK'|'MONTH'|'YEAR', string>
     */
    protected array $recurringOrderFrequencyMappings = [];

    /** @var array<PosInterface::MODEL_*, string> */
    protected array $secureTypeMappings = [];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [];

    /**
     * by default we set ISO 4217 currency values.
     * Some gateways may use different currency values.
     *
     * @var non-empty-array<PosInterface::CURRENCY_*, string|int>
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
     * @return array<PosInterface::TX_TYPE_*, string|array<PosInterface::MODEL_*, string>>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @inheritDoc
     */
    public function mapTxType(string $txType, ?string $paymentModel = null, ?array $order = null): string
    {
        if (!$this->isSupportedTxType($txType, $paymentModel)) {
            throw new UnsupportedTransactionTypeException();
        }

        if (\is_string($this->txTypeMappings[$txType])) {
            return $this->txTypeMappings[$txType];
        }

        return $this->txTypeMappings[$txType][$paymentModel];
    }

    /**
     * @return array<PosInterface::MODEL_*, string>
     */
    public function getSecureTypeMappings(): array
    {
        return $this->secureTypeMappings;
    }

    /**
     * @inheritDoc
     */
    public function mapSecureType(string $paymentModel): string
    {
        if ([] === $this->secureTypeMappings) {
            throw new \LogicException('Security type mappings are not supported.');
        }

        return $this->secureTypeMappings[$paymentModel];
    }

    /**
     * @inheritDoc
     */
    public function mapCurrency(string $currency)
    {
        return $this->currencyMappings[$currency];
    }

    /**
     * @inheritDoc
     */
    public function getCurrencyMappings(): array
    {
        return $this->currencyMappings;
    }

    /**
     * @inheritDoc
     */
    public function getRecurringOrderFrequencyMappings(): array
    {
        return $this->recurringOrderFrequencyMappings;
    }

    /**
     * @inheritDoc
     */
    public function mapRecurringFrequency(string $period): string
    {
        if ([] === $this->recurringOrderFrequencyMappings) {
            throw new \LogicException('Recurring frequency mappings are not supported.');
        }

        return $this->recurringOrderFrequencyMappings[$period];
    }

    /**
     * @inheritDoc
     */
    public function mapLang(string $lang): string
    {
        if ([] === $this->langMappings) {
            throw new \LogicException('Language mappings are not supported.');
        }

        return $this->langMappings[$lang]
            ?? $this->langMappings[PosInterface::LANG_TR]
            ?? $lang;
    }

    /**
     * @inheritDoc
     */
    public function getLangMappings(): array
    {
        return $this->langMappings;
    }

    /**
     * @inheritDoc
     */
    public function getCardTypeMappings(): array
    {
        return $this->cardTypeMappings;
    }

    /**
     * @inheritDoc
     */
    public function mapCardType(string $cardType): string
    {
        if ([] === $this->cardTypeMappings) {
            throw new \LogicException('Card type mappings are not supported.');
        }

        return $this->cardTypeMappings[$cardType];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_*    $txType
     * @phpstan-param PosInterface::MODEL_*|null $paymentModel
     *
     * @param string      $txType
     * @param string|null $paymentModel
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    protected function isSupportedTxType(string $txType, ?string $paymentModel = null): bool
    {
        if (!isset($this->txTypeMappings[$txType])) {
            return false;
        }

        if (\is_array($this->txTypeMappings[$txType])) {
            if (null === $paymentModel) {
                throw new \InvalidArgumentException(
                    sprintf('$paymentModel must be provided for the transaction type %s', $txType)
                );
            }

            return isset($this->txTypeMappings[$txType][$paymentModel]);
        }

        return true;
    }
}
