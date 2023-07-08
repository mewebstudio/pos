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
    /** @var string */
    public const API_VERSION = '1.0.0';

    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /**
     * {@inheritdoc}
     */
    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3',
        AbstractGateway::MODEL_NON_SECURE => '0',
    ];

    /**
     * {@inheritDoc}
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY    => 'Sale',
        AbstractGateway::TX_CANCEL => 'SaleReversal',
        AbstractGateway::TX_STATUS => 'GetMerchantOrderDetail',
        AbstractGateway::TX_REFUND => 'PartialDrawback', // Also there is a "Drawback"
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
        return (int) (round($amount, 2) * 100);
    }

    /**
     * @param KuveytPosAccount $account
     *
     * {@inheritDoc}
     * @return array{APIVersion: string, HashData: string, CustomerIPAddress: mixed, KuveytTurkVPosAdditionalData: array{AdditionalData: array{Key: string, Data: mixed}}, TransactionType: string, InstallmentCount: mixed, Amount: mixed, DisplayAmount: int, CurrencyCode: mixed, MerchantOrderId: mixed, TransactionSecurity: mixed, MerchantId: string, CustomerId: string, UserName: string}
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

        if ($card !== null) {
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
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = 0;
        $hash = $this->crypt->createHash($account, $mappedOrder);

        return [
            'IsFromExternalNetwork' => true,
            'BusinessKey' => 0,
            'ResourceId' => 0,
            'ActionId' => 0,
            'LanguageId' => 0,
            'CustomerId' => null,
            'MailOrTelephoneOrder' => true,
            'Amount' => 0,
            'MerchantId' => $account->getClientId(),
            'MerchantOrderId' => $order->id,
            /**
             * Eğer döndüğümüz orderid ile aratılırsa yalnızca aranan işlem gelir.
             * 0 değeri girilirse tarih aralığındaki aynı merchanorderid'ye ait tüm siparişleri getirir.
             * uniq değer orderid'dir, işlemi birebir yakalamak için orderid değeri atanmalıdır.
             */
            'OrderId' => 0,
            /**
             * Test ortamda denendiginde, StartDate ve EndDate her hangi bir tarih atandiginda istek calisiyor,
             * siparisi buluyor.
             * Ancak bu degerler gonderilmediginde veya gecersiz (orn. null) gonderildiginde SOAP server hata donuyor.
             */
            'StartDate' =>  $order->start_date->format('Y-m-d\TH:i:s'),
            'EndDate' => $order->end_date->format('Y-m-d\TH:i:s'),
            'TransactionType' => 0,
            'VPosMessage' => $this->getRequestAccountData($account) + [
                'APIVersion' => self::API_VERSION,
                'InstallmentMaturityCommisionFlag' => 0,
                'HashData' => $hash,
                'SubMerchantId' => 0,
                'CardType' => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], // Default gönderilebilir.
                'BatchID' => 0,
                'TransactionType' => $this->mapTxType(AbstractGateway::TX_STATUS),
                'InstallmentCount' => 0,
                'Amount' => 0,
                'DisplayAmount' => 0,
                'CancelAmount' => 0,
                'MerchantOrderId' => $order->id,
                'CurrencyCode' => $this->mapCurrency($order->currency),
                'FECAmount' => 0,
                'QeryId' => 0,
                'DebtId' => 0,
                'SurchargeAmount' => 0,
                'SGKDebtAmount' => 0,
                'TransactionSecurity' => 1,
            ]
        ];
    }

    /**
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = self::amountFormat($order->amount);
        $hash = $this->crypt->createHash($account, $mappedOrder);

        return [
            'IsFromExternalNetwork' => true,
            'BusinessKey' => 0,
            'ResourceId' => 0,
            'ActionId' => 0,
            'LanguageId' => 0,
            'CustomerId' => $account->getCustomerId(),
            'MailOrTelephoneOrder' => true,
            'Amount' => self::amountFormat($order->amount),
            'MerchantId' => $account->getClientId(),
            'OrderId' => $order->id,
            'RRN' => $order->ref_ret_num,
            'Stan' => $order->trans_id,
            'ProvisionNumber' => $order->auth_code,
            'TransactionType' => 0,
            'VPosMessage' => $this->getRequestAccountData($account) + [
                'APIVersion' => self::API_VERSION,
                'InstallmentMaturityCommisionFlag' => 0,
                'HashData' => $hash,
                'SubMerchantId' => 0,
                'CardType' => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], //Default gönderilebilir.
                'BatchID' => 0,
                'TransactionType' => $this->mapTxType(AbstractGateway::TX_CANCEL),
                'InstallmentCount' => 0,
                'Amount' => self::amountFormat($order->amount),
                'DisplayAmount' => self::amountFormat($order->amount),
                'CancelAmount' => self::amountFormat($order->amount),
                'MerchantOrderId' => $order->id,
                'FECAmount' => 0,
                'CurrencyCode' => $this->mapCurrency($order->currency),
                'QeryId' => 0,
                'DebtId' => 0,
                'SurchargeAmount' => 0,
                'SGKDebtAmount' => 0,
                'TransactionSecurity' => 1,
            ]
        ];
    }

    /**
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = self::amountFormat($order->amount);
        $hash = $this->crypt->createHash($account, $mappedOrder);

        return [
            'IsFromExternalNetwork' => true,
            'BusinessKey' => 0,
            'ResourceId' => 0,
            'ActionId' => 0,
            'LanguageId' => 0,
            'CustomerId' => $account->getCustomerId(),
            'MailOrTelephoneOrder' => true,
            'Amount' => self::amountFormat($order->amount),
            'MerchantId' => $account->getClientId(),
            'OrderId' => $order->id,
            'RRN' => $order->ref_ret_num,
            'Stan' => $order->trans_id,
            'ProvisionNumber' => $order->auth_code,
            'TransactionType' => 0,
            'VPosMessage' => $this->getRequestAccountData($account) + [
                'APIVersion' => self::API_VERSION,
                'InstallmentMaturityCommisionFlag' => 0,
                'HashData' => $hash,
                'SubMerchantId' => 0,
                'CardType' => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], //Default gönderilebilir.
                'BatchID' => 0,
                'TransactionType' => $this->mapTxType(AbstractGateway::TX_REFUND),
                'InstallmentCount' => 0,
                'Amount' => self::amountFormat($order->amount),
                'DisplayAmount' => 0,
                'CancelAmount' => self::amountFormat($order->amount),
                'MerchantOrderId' => $order->id,
                'FECAmount' => 0,
                'CurrencyCode' => $this->mapCurrency($order->currency),
                'QeryId' => 0,
                'DebtId' => 0,
                'SurchargeAmount' => 0,
                'SGKDebtAmount' => 0,
                'TransactionSecurity' => 1,
            ]
        ];
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

    public function mapInstallment(?int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }

    /**
     * @param KuveytPosAccount $account
     *
     * @return array{MerchantId: string, CustomerId: string, UserName: string}
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
