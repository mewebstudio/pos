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
use Mews\Pos\PosInterface;

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
        PosInterface::MODEL_3D_SECURE  => '3',
        PosInterface::MODEL_NON_SECURE => '0',
    ];

    /**
     * {@inheritDoc}
     */
    protected $txTypeMappings = [
        PosInterface::TX_PAY    => 'Sale',
        PosInterface::TX_CANCEL => 'SaleReversal',
        PosInterface::TX_STATUS => 'GetMerchantOrderDetail',
        PosInterface::TX_REFUND => 'PartialDrawback', // Also there is a "Drawback"
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
        PosInterface::CURRENCY_TRY => '0949',
        PosInterface::CURRENCY_USD => '0840',
        PosInterface::CURRENCY_EUR => '0978',
        PosInterface::CURRENCY_GBP => '0826',
        PosInterface::CURRENCY_JPY => '0392',
        PosInterface::CURRENCY_RUB => '0810',
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
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = $this->getRequestAccountData($account) + [
                'APIVersion'                   => self::API_VERSION,
                'HashData'                     => '',
                'CustomerIPAddress'            => $order['ip'],
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

        $result['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param KuveytPosAccount                     $account
     * @param array<string, int|string|float|null> $order
     * @param PosInterface::MODEL_*                $paymentModel
     * @param PosInterface::TX_*                   $txType
     * @param AbstractCreditCard                   $card
     */
    public function create3DEnrollmentCheckRequestData(KuveytPosAccount $account, array $order, string $paymentModel, string $txType, ?AbstractCreditCard $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = $this->getRequestAccountData($account) + [
                'APIVersion'          => self::API_VERSION,
                'TransactionType'     => $this->mapTxType($txType),
                'TransactionSecurity' => $this->secureTypeMappings[$paymentModel],
                'InstallmentCount'    => $this->mapInstallment($order['installment']),
                'Amount'              => self::amountFormat($order['amount']),
                //DisplayAmount: Amount değeri ile aynı olacak şekilde gönderilmelidir.
                'DisplayAmount'       => self::amountFormat($order['amount']),
                'CurrencyCode'        => $this->mapCurrency($order['currency']),
                'MerchantOrderId'     => $order['id'],
                'OkUrl'               => $order['success_url'],
                'FailUrl'             => $order['fail_url'],
            ];

        if ($card instanceof AbstractCreditCard) {
            $inputs['CardHolderName']      = $card->getHolderName();
            $inputs['CardType']            = $this->cardTypeMapping[$card->getType()];
            $inputs['CardNumber']          = $card->getNumber();
            $inputs['CardExpireDateYear']  = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['CardExpireDateMonth'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['CardCVV2']            = $card->getCvv();
        }

        $inputs['HashData'] = $this->crypt->create3DHash($account, $inputs);

        return $inputs;
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, AbstractCreditCard $card): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => null,
            'MailOrTelephoneOrder'  => true,
            'Amount'                => 0,
            'MerchantId'            => $account->getClientId(),
            'MerchantOrderId'       => $order['id'],
            /**
             * Eğer döndüğümüz orderid ile aratılırsa yalnızca aranan işlem gelir.
             * 0 değeri girilirse tarih aralığındaki aynı merchanorderid'ye ait tüm siparişleri getirir.
             * uniq değer orderid'dir, işlemi birebir yakalamak için orderid değeri atanmalıdır.
             */
            'OrderId'               => $order['remote_order_id'] ?? 0,
            /**
             * Test ortamda denendiginde, StartDate ve EndDate her hangi bir tarih atandiginda istek calisiyor,
             * siparisi buluyor.
             * Ancak bu degerler gonderilmediginde veya gecersiz (orn. null) gonderildiginde SOAP server hata donuyor.
             */
            'StartDate'             => $order['start_date']->format('Y-m-d\TH:i:s'),
            'EndDate'               => $order['end_date']->format('Y-m-d\TH:i:s'),
            'TransactionType'       => 0,
            'VPosMessage'           => $this->getRequestAccountData($account) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], // Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->mapTxType(PosInterface::TX_STATUS),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 0,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 0,
                    'MerchantOrderId'                  => $order['id'],
                    'CurrencyCode'                     => $this->mapCurrency($order['currency']),
                    'FECAmount'                        => 0,
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($account, $result['VPosMessage']);

        return $result;
    }

    /**
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => $account->getCustomerId(),
            'MailOrTelephoneOrder'  => true,
            'Amount'                => self::amountFormat($order['amount']),
            'MerchantId'            => $account->getClientId(),
            'OrderId'               => $order['remote_order_id'],
            'RRN'                   => $order['ref_ret_num'],
            'Stan'                  => $order['trans_id'],
            'ProvisionNumber'       => $order['auth_code'],
            'TransactionType'       => 0,
            'VPosMessage'           => $this->getRequestAccountData($account) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], //Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->mapTxType(PosInterface::TX_CANCEL),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => self::amountFormat($order['amount']),
                    'DisplayAmount'                    => self::amountFormat($order['amount']),
                    'CancelAmount'                     => self::amountFormat($order['amount']),
                    'MerchantOrderId'                  => $order['id'],
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => $this->mapCurrency($order['currency']),
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($account, $result['VPosMessage']);

        return $result;
    }

    /**
     * @param KuveytPosAccount $account
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => $account->getCustomerId(),
            'MailOrTelephoneOrder'  => true,
            'Amount'                => self::amountFormat($order['amount']),
            'MerchantId'            => $account->getClientId(),
            'OrderId'               => $order['remote_order_id'],
            'RRN'                   => $order['ref_ret_num'],
            'Stan'                  => $order['trans_id'],
            'ProvisionNumber'       => $order['auth_code'],
            'TransactionType'       => 0,
            'VPosMessage'           => $this->getRequestAccountData($account) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->cardTypeMapping[AbstractCreditCard::CARD_TYPE_VISA], //Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->mapTxType(PosInterface::TX_REFUND),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => self::amountFormat($order['amount']),
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => self::amountFormat($order['amount']),
                    'MerchantOrderId'                  => $order['id'],
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => $this->mapCurrency($order['currency']),
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($account, $result['VPosMessage']);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $order Kuveyt bank'tan donen HTML cevaptan parse edilen form inputlar
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $order,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }

    public function mapInstallment(?int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return array_merge($order, [
            'id'         => $order['id'],
            'currency'   => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'start_date' => $order['start_date'] ?? date_create('-360 day'),
            'end_date'   => $order['end_date'] ?? date_create(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return array_merge($order, [
            'id'              => $order['id'],
            'remote_order_id' => $order['remote_order_id'],
            'ref_ret_num'     => $order['ref_ret_num'],
            'auth_code'       => $order['auth_code'],
            'trans_id'        => $order['trans_id'],
            'amount'          => $order['amount'],
            'currency'        => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return array_merge($order, [
            'id'              => $order['id'],
            'remote_order_id' => $order['remote_order_id'],
            'ref_ret_num'     => $order['ref_ret_num'],
            'auth_code'       => $order['auth_code'],
            'trans_id'        => $order['trans_id'],
            'amount'          => $order['amount'],
            'currency'        => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
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
