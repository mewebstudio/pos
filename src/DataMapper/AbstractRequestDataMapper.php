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
    /** @var array<AbstractGateway::MODEL_*, string> */
    protected $secureTypeMappings = [];

    /**
     * Transaction Types
     *
     * @var array<AbstractGateway::TX_*, string>
     */
    protected $txTypeMappings = [];

    /** @var array<AbstractCreditCard::CARD_TYPE_*, string> */
    protected $cardTypeMapping = [];

    /** @var array<AbstractGateway::LANG_*, string> */
    protected $langMappings = [
        AbstractGateway::LANG_TR => 'tr',
        AbstractGateway::LANG_EN => 'en',
    ];

    /**
     * default olarak ISO 4217 kodlar tanimliyoruz.
     * fakat bazi banklar ISO standarti kullanmiyorlar.
     * Currency mapping
     *
     * @var non-empty-array<string, string>
     */
    protected $currencyMappings = [
        'TRY' => '949',
        'USD' => '840',
        'EUR' => '978',
        'GBP' => '826',
        'JPY' => '392',
        'RUB' => '643',
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
     * @param CryptInterface|null   $crypt
     * @param array<string, string> $currencyMappings
     */
    public function __construct(?CryptInterface $crypt = null, array $currencyMappings = [])
    {
        $this->crypt = $crypt;
        if ($currencyMappings !== []) {
            $this->currencyMappings = $currencyMappings;
        }
    }

    /**
     * @phpstan-param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     *
     * @param AbstractPosAccount                                          $account
     * @param array<string, string|int|float|null>                        $order
     * @param array                                                       $responseData gateway'den gelen cevap
     *
     * @return array
     */
    abstract public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array;

    /**
     * @phpstan-param AbstractGateway::TX_*        $txType
     *
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param AbstractCreditCard|null              $card
     *
     * @return array
     */
    abstract public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param AbstractCreditCard|null              $card
     *
     * @return array
     */
    abstract public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array
     */
    abstract public function createStatusRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array
     */
    abstract public function createCancelRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     *
     * @return array
     */
    abstract public function createRefundRequestData(AbstractPosAccount $account, array $order): array;

    /**
     * @phpstan-param AbstractGateway::TX_*        $txType
     * @phpstan-param AbstractGateway::MODEL_*     $paymentModel
     *
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param string                               $gatewayURL
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param AbstractCreditCard|null              $card
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    abstract public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount                   $account
     * @param array<string, string|int|float|null> $order
     * @param array<string, string|int|float|null> $extraData bankaya gore degisen ozel degerler
     *
     * @return array
     */
    abstract public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array;

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
     * @return non-empty-array<string, string>
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

    abstract public function mapInstallment(?int $installment): string;

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

    /**
     * prepares order for payment request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function preparePaymentOrder(array $order): object
    {
        return (object) $order;
    }

    /**
     * prepares order for TX_POST_PAY type request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function preparePostPaymentOrder(array $order): object
    {
        return (object) $order;
    }

    /**
     * prepares order for order status request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function prepareStatusOrder(array $order): object
    {
        return (object) $order;
    }

    /**
     * prepares order for cancel request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function prepareCancelOrder(array $order): object
    {
        return (object) $order;
    }

    /**
     * prepares order for refund request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function prepareRefundOrder(array $order): object
    {
        return (object) $order;
    }

    /**
     * prepares order for history request
     *
     * @param array<string, mixed> $order
     *
     * @return object
     */
    protected function prepareHistoryOrder(array $order): object
    {
        return (object) $order;
    }
}
