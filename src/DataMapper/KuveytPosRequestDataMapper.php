<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class KuveytPosRequestDataMapper extends AbstractRequestDataMapper
{
    public const API_VERSION = '1.0.0';
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => 3,
        //todo update null values with valid values
        AbstractGateway::MODEL_3D_PAY     => null,
        AbstractGateway::MODEL_3D_HOST    => null,
        AbstractGateway::MODEL_NON_SECURE => 0,
    ];

    /**
     * @inheritdoc
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Sale',
        //todo update null values with valid values
        AbstractGateway::TX_PRE_PAY  => null,
        AbstractGateway::TX_POST_PAY => null,
        AbstractGateway::TX_CANCEL   => null,
        AbstractGateway::TX_REFUND   => null,
        AbstractGateway::TX_STATUS   => null,
    ];

    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => 'Visa',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => 'MasterCard',
    ];

    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencyMappings = [
        'TRY' => '0949',
        'USD' => '0840',
        'EUR' => '0978',
        'GBP' => '0826',
        'JPY' => '0392',
        'RUB' => '0810',
    ];

    /**
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
     * @param float $amount
     *
     * @return int
     */
    public static function amountFormat(float $amount): int
    {
        return round($amount, 2) * 100;
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $hash = $this->create3DHash($account, $order, $txType, true);

        return [
            'APIVersion'                   => self::API_VERSION,
            'HashData'                     => $hash,
            'MerchantId'                   => $account->getClientId(),
            'CustomerId'                   => $account->getCustomerId(),
            'UserName'                     => $account->getUsername(),
            'CustomerIPAddress'            => $order->ip,
            'KuveytTurkVPosAdditionalData' => [
                'AdditionalData' => [
                    'Key'  => 'MD',
                    'Data' => $responseData['MD'],
                ],
            ],
            'TransactionType'              => $responseData['VPosMessage']['TransactionType'],
            'InstallmentCount'             => $responseData['VPosMessage']['InstallmentCount'],
            'Amount'                       => $responseData['VPosMessage']['Amount'],
            'DisplayAmount'                => $responseData['VPosMessage']['DisplayAmount'],
            'CurrencyCode'                 => $responseData['VPosMessage']['CurrencyCode'],
            'MerchantOrderId'              => $responseData['VPosMessage']['MerchantOrderId'],
            'TransactionSecurity'          => $responseData['VPosMessage']['TransactionSecurity'],
        ];
    }

    /**
     * @param KuveytPosAccount        $account
     * @param                         $order
     * @param string                  $txType
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    public function create3DEnrollmentCheckRequestData(KuveytPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $hash = $this->create3DHash($account, $order, $txType);

        $inputs = [
            'APIVersion'          => self::API_VERSION,
            'MerchantId'          => $account->getClientId(),
            'UserName'            => $account->getUsername(),
            'CustomerId'          => $account->getCustomerId(),
            'HashData'            => $hash,
            'TransactionType'     => $this->mapTxType($txType),
            'TransactionSecurity' => $this->secureTypeMappings[$account->getModel()],
            'InstallmentCount'    => $this->mapInstallment($order->installment),
            'Amount'              => self::amountFormat($order->amount),
            //DisplayAmount: Amount değeri ile aynı olacak şekilde gönderilmelidir.
            'DisplayAmount'       => self::amountFormat($order->amount),
            'CurrencyCode'        => $this->mapCurrency($order->currency),
            'MerchantOrderId'     => $order->id,
            'OkUrl'               => $order->success_url,
            'FailUrl'             => $order->fail_url,
        ];

        if ($card) {
            $inputs['CardHolderName']      = $card->getHolderName();
            $inputs['CardType']            = $this->cardTypeMapping[$card->getType()];
            $inputs['CardNumber']          = $card->getNumber();
            $inputs['CardExpireDateYear']  = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['CardExpireDateMonth'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['CardCVV2']            = $card->getCvv();
        }

        return $inputs;
    }

    /**
     * Create 3D Hash
     * todo Şifrelenen veriler (Hashdata) uyuşmamaktadır. hatasi aliyoruz
     *
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $txType
     * @param bool               $forProvision
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType, bool $forProvision = false): string
    {
        $hashedPassword = $this->hashString($account->getStoreKey());

        if ($forProvision) {
            $hashData = $this->createHashDataForAuthorization($account, $order, $hashedPassword);
        } else {
            $hashData = $this->createHashDataForProvision($account, $order, $hashedPassword);
        }

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritdoc
     */
    public function mapInstallment(?int $installment)
    {
        return $installment > 1 ? $installment : 0;
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $hashedPassword
     *
     * @return array
     */
    private function createHashDataForAuthorization(AbstractPosAccount $account, $order, string $hashedPassword): array
    {
        return [
            $account->getClientId(),
            $order->id,
            self::amountFormat($order->amount),
            $account->getUsername(),
            $hashedPassword,
        ];
    }

    /**
     * @param AbstractPosAccount $account
     * @param                    $order
     * @param string             $hashedPassword
     *
     * @return array
     */
    private function createHashDataForProvision(AbstractPosAccount $account, $order, string $hashedPassword): array
    {
        return [
            $account->getClientId(),
            $order->id,
            self::amountFormat($order->amount),
            $order->success_url,
            $order->fail_url,
            $account->getUsername(),
            $hashedPassword,
        ];
    }
}
