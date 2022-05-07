<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * AbstractRequestDataMapper
 */
abstract class AbstractRequestDataMapper
{
    protected const HASH_ALGORITHM = 'sha1';
    protected const HASH_SEPARATOR = '';

    protected $secureTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array
     */
    protected $txTypeMappings = [];

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
     * @var array
     */
    protected $recurringOrderFrequencyMapping = [];

    /** @var bool */
    protected $testMode = false;

    /**
     * @param array $currencyMappings
     */
    public function __construct(array $currencyMappings = [])
    {
        if (!empty($currencyMappings)) {
            $this->currencyMappings = $currencyMappings;
        }
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $txType       ex: AbstractGateway::TX_PAY
     * @param array              $responseData gateway'den gelen cevap
     *
     * @return array
     */
    abstract public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array;

    /**
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $txType  ex: AbstractGateway::TX_PAY
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
     * @param                    $order
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
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $txType     ex: AbstractGateway::TX_PAY
     * @param string                  $gatewayURL
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $txType  mapped value from AbstractGateway::TX_PAY
     *
     * @return string
     */
    abstract public function create3DHash(AbstractPosAccount $account, $order, string $txType): string;

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param array              $extraData bankaya gore degisen ozel degerler
     *
     * @return array
     */
    abstract public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array;

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
     * @return array
     */
    public function getCardTypeMapping(): array
    {
        return $this->cardTypeMapping;
    }

    /**
     * @return array
     */
    public function getSecureTypeMappings(): array
    {
        return $this->secureTypeMappings;
    }

    /**
     * @return array
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
     * @return array
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
     * @param string $str
     *
     * @return string
     */
    protected function hashString(string $str): string
    {
        return base64_encode(hash(static::HASH_ALGORITHM, $str, true));
    }

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
