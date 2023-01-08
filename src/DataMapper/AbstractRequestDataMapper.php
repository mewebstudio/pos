<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * AbstractRequestDataMapper
 */
abstract class AbstractRequestDataMapper
{
    /**
     * @var array<AbstractGateway::MODEL_*, string>
     */
    protected $secureTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array<AbstractGateway::TX_*, string>
     */
    protected $txTypeMappings = [];

    /** @var array<AbstractCreditCard::CARD_TYPE_*, string> */
    protected $cardTypeMapping = [];

    protected $langMappings = [
        AbstractGateway::LANG_TR => 'tr',
        AbstractGateway::LANG_EN => 'en',
    ];

    /**
     * default olarak ISO 4217 kodlar tanimliyoruz.
     * fakat bazi banklar ISO standarti kullanmiyorlar.
     * Currency mapping
     *
     * @var array
     */
    protected $currencyMappings = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    /**
     * period mapping for recurring orders
     * @var array<'DAY'|'WEEK'|'MONTH'|'YEAR', string>
     */
    protected $recurringOrderFrequencyMapping = [];

    /** @var bool */
    protected $testMode = false;

    /** @var CryptInterface|null */
    protected $crypt;

    /**
     * @param CryptInterface|null $crypt
     * @param array               $currencyMappings
     */
    public function __construct(?CryptInterface $crypt = null, array $currencyMappings = [])
    {
        $this->crypt = $crypt;
        if (count($currencyMappings) > 0) {
            $this->currencyMappings = $currencyMappings;
        }
    }

    /**
     * @phpstan-param AbstractGateway::TX_* $txType
     *
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param array              $responseData gateway'den gelen cevap
     *
     * @return array
     */
    abstract public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array;

    /**
     * @phpstan-param AbstractGateway::TX_* $txType
     *
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    abstract public function createStatusRequestData(AbstractPosAccount $account, $order): array;

    /**
     * @param AbstractPosAccount $account
     * @param object             $order
     *
     * @return array
     */
    abstract public function createCancelRequestData(AbstractPosAccount $account, $order): array;

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return array
     */
    abstract public function createRefundRequestData(AbstractPosAccount $account, $order): array;

    /**
     * @phpstan-param AbstractGateway::TX_* $txType
     *
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $gatewayURL
     * @param string                  $txType
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param array              $extraData bankaya gore degisen ozel degerler
     *
     * @return array
     */
    abstract public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array;

    /**
     * @return CryptInterface
     */
    public function getCrypt(): CryptInterface
    {
        return $this->crypt;
    }

    /**
     * @return bool
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
     * @return array<AbstractGateway::MODEL_*, string>
     */
    public function getSecureTypeMappings(): array
    {
        return $this->secureTypeMappings;
    }

    /**
     * @return array<AbstractGateway::TX_*, string>
     */
    public function getTxTypeMappings(): array
    {
        return $this->txTypeMappings;
    }

    /**
     * @return array
     */
    public function getCurrencyMappings(): array
    {
        return $this->currencyMappings;
    }


    /**
     * @param bool $testMode
     *
     * @return AbstractRequestDataMapper
     */
    public function setTestMode(bool $testMode): self
    {
        $this->testMode = $testMode;

        return $this;
    }

    /**
     * @param string $currency TRY, USD
     *
     * @return string currency code that is accepted by bank
     */
    public function mapCurrency(string $currency): string
    {
        return $this->currencyMappings[$currency] ?? $currency;
    }

    /**
     * @phpstan-param AbstractGateway::TX_* $txType
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
     * @param int|null $installment
     *
     * @return int|string
     */
    abstract public function mapInstallment(?int $installment);

    /**
     * bank returns error messages for specified language value
     * usually accepted values are tr,en
     *
     * @param AbstractPosAccount $account
     * @param                    $order
     *
     * @return string
     */
    protected function getLang(AbstractPosAccount $account, $order): string
    {
        if ($order && isset($order->lang)) {
            return $this->langMappings[$order->lang];
        }

        return $this->langMappings[$account->getLang()];
    }
}
