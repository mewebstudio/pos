<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class KuveytPosRequestDataMapper extends AbstractRequestDataMapperCrypt
{
    public const API_VERSION = '1.0.0';
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /**
     * {@inheritdoc}
     */
    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => 3,
        AbstractGateway::MODEL_NON_SECURE => 0,
    ];

    /**
     * {@inheritDoc}
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Sale',
    ];

    /**
     * {@inheritDoc}
     */
    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => 'Visa',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => 'MasterCard',
        AbstractCreditCard::CARD_TYPE_TROY       => 'Troy',
    ];

    /**
     * Currency mapping
     *
     * {@inheritdoc}
     */
    protected $currencyMappings = [
        'TRY' => '0949',
        'USD' => '0840',
        'EUR' => '0978',
        'GBP' => '0826',
        'JPY' => '0392',
        'RUB' => '0810',
    ];

    /** @var CryptInterface|KuveytPosCrypt */
    protected $crypt;

    /**
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
     *
     * @param float $amount
     *
     * @return int
     */
    public static function amountFormat(float $amount): int
    {
        return intval(round($amount, 2) * 100);
    }

    /**
     * @param KuveytPosAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = self::amountFormat($order->amount);
        $hash = $this->crypt->createHash($account, $mappedOrder, $this->mapTxType($txType));

        return $this->getRequestAccountData($account) + [
            'APIVersion'                   => self::API_VERSION,
            'HashData'                     => $hash,
            'CustomerIPAddress'            => $order->ip,
            'KuveytTurkVPosAdditionalData' => [
                'AdditionalData' => [
                    'Key'  => 'MD',
                    'Data' => $responseData['MD'],
                ],
            ],
            'TransactionType'              => $this->mapTxType($txType),
            'InstallmentCount'             => $responseData['VPosMessage']['InstallmentCount'],
            'Amount'                       => $responseData['VPosMessage']['Amount'],
            'DisplayAmount'                => self::amountFormat($responseData['VPosMessage']['Amount']),
            'CurrencyCode'                 => $responseData['VPosMessage']['CurrencyCode'],
            'MerchantOrderId'              => $responseData['VPosMessage']['MerchantOrderId'],
            'TransactionSecurity'          => $responseData['VPosMessage']['TransactionSecurity'],
        ];
    }

    /**
     * @param KuveytPosAccount      $account
     * @param AbstractGateway::TX_* $txType
     */
    public function create3DEnrollmentCheckRequestData(KuveytPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = self::amountFormat($order->amount);
        $hash = $this->crypt->create3DHash($account, $mappedOrder, $this->mapTxType($txType));

        $inputs = $this->getRequestAccountData($account) + [
            'APIVersion'          => self::API_VERSION,
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
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function mapInstallment(?int $installment)
    {
        return $installment > 1 ? $installment : 0;
    }

    /**
     * @param KuveytPosAccount $account
     *
     * @return array
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'MerchantId' => $account->getClientId(),
            'CustomerId' => $account->getCustomerId(),
            'UserName'   => $account->getUsername(),
        ];
    }
}
