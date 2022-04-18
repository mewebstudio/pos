<?php

namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

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

    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencyMappings = [];

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
     * @param string             $txType       mapped value from AbstractGateway::TX_PAY
     * @param array              $responseData gateway'den gelen cevap
     *
     * @return array
     */
    abstract public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array;

    /**
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $txType  mapped value from AbstractGateway::TX_PAY
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array;

    /**
     * @param AbstractPosAccount      $account
     * @param                         $order
     * @param string                  $txType  mapped value from AbstractGateway::TX_PAY
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    abstract public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array;

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
     * @param string                  $txType     mapped value from AbstractGateway::TX_PAY
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
     * @return string
     */
    abstract public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array;

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
            return $order->lang;
        }

        return $account->getLang();
    }
}
