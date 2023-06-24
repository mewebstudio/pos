<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper;

use Exception;
use InvalidArgumentException;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for PosNetV1Pos Gateway requests
 */
class PosNetV1PosRequestDataMapper extends AbstractRequestDataMapperCrypt
{
    public const API_VERSION = 'V100';
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'ym';

    /**
     * PosNet requires order id with specific length
     */
    private const ORDER_ID_LENGTH = 20;
    /**
     * order id total length including prefix;
     */
    private const ORDER_ID_TOTAL_LENGTH = 24;

    private const ORDER_ID_3D_PREFIX = 'TDS_';
    private const ORDER_ID_3D_PAY_PREFIX = '';  //?
    private const ORDER_ID_REGULAR_PREFIX = '';  //?

    /**
     * {@inheritDoc}
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Sale',
        AbstractGateway::TX_PRE_PAY  => 'Auth',
        AbstractGateway::TX_POST_PAY => 'Capture',
        AbstractGateway::TX_CANCEL   => 'Reverse',
        AbstractGateway::TX_REFUND   => 'Return',
        AbstractGateway::TX_STATUS   => 'TransactionInquiry',
    ];

    /**
     * {@inheritDoc}
     */
    protected $currencyMappings = [
        'TRY' => 'TL',
        'USD' => 'US',
        'EUR' => 'EU',
        'GBP' => 'GB',
        'JPY' => 'JP',
        'RUB' => 'RU',
    ];

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $requestData = [
            'ApiType'               => 'JSON',
            'ApiVersion'            => self::API_VERSION,
            'MerchantNo'            => $account->getClientId(),
            'TerminalNo'            => $account->getTerminalId(),
            'PaymentInstrumentType' => 'CARD',
            'IsEncrypted'           => 'N',
            'IsTDSecureMerchant'    => 'Y',
            'IsMailOrder'           => 'N',
            'ThreeDSecureData'      => [
                'SecureTransactionId' => $responseData['SecureTransactionId'],
                'CavvData'            => $responseData['CAVV'],
                'Eci'                 => $responseData['ECI'],
                'MdStatus'            => (int) $responseData['MdStatus'],
                'MD'                  => $responseData['MD'],
            ],
            'MACParams'             => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
            'Amount'                => self::amountFormat($order->amount),
            'CurrencyCode'          => self::mapCurrency($order->currency),
            'PointAmount'           => 0,
            'OrderId'               => self::formatOrderId($order->id),
            'InstallmentCount'      => $this->mapInstallment($order->installment),
            'InstallmentType'       => 'N',
        ];

        if ($order->installment > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        $requestData['MAC'] = $this->crypt->createHash($account, $requestData);

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        if (null === $card) {
            throw new \LogicException('Eksik kart bilgileri!');
        }
        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
            'MerchantNo'             => $account->getClientId(),
            'TerminalNo'             => $account->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'AdditionalInfoData'     => null,
            'CardInformationData'    => [
                'CardNo'         => $card->getNumber(),
                'ExpireDate'     => $card->getExpirationDate(),
                'Cvc2'           => $card->getCvv(),
                'CardHolderName' => $card->getHolderName(),
            ],
            'IsMailOrder'            => 'N',
            'IsRecurring'            => null,
            'IsTDSecureMerchant'     => null,
            'PaymentInstrumentType'  => 'CARD',
            'ThreeDSecureData'       => null,
            'Amount'                 => self::amountFormat($order->amount),
            'CurrencyCode'           => $this->mapCurrency($order->currency),
            'OrderId'                => self::formatOrderId($order->id),
            'InstallmentCount'       => $this->mapInstallment($order->installment),
            'InstallmentType'        => 'N',
            'KOICode'                => null,
            'MerchantMessageData'    => null,
            'PointAmount'            => null,
        ];

        if ($order->installment > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($account->getStoreKey(), $requestData, 'MACParams', ':');

        if (isset($order->koiCode) && $order->koiCode > 0) {
            $requestData['KOICode'] = $order->koiCode;
        }

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo',
            'MerchantNo'             => $account->getClientId(),
            'TerminalNo'             => $account->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'Amount'                 => self::amountFormat($order->amount),
            'CurrencyCode'           => $this->mapCurrency($order->currency),
            'ReferenceCode'          => $order->ref_ret_num,
            'InstallmentCount'       => $this->mapInstallment($order->installment),
            'InstallmentType'        => 'N',
        ];

        if ($order->installment > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($account->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $account->getClientId(),
            'TerminalNo'             => $account->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => 'N',
            'PaymentFacilitatorData' => null,
            'OrderId'                => self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel()),
        ];

        $requestData['MAC'] = $this->crypt->hashFromParams($account->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $account->getClientId(),
            'TerminalNo'             => $account->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->mapTxType($order->transaction_type),
        ];

        if (isset($order->ref_ret_num)) {
            $requestData['ReferenceCode'] = $order->ref_ret_num;
        } else {
            $requestData['OrderId'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($account->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $account->getClientId(),
            'TerminalNo'             => $account->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->mapTxType($order->transaction_type),
        ];

        if (isset($order->ref_ret_num)) {
            $requestData['ReferenceCode'] = $order->ref_ret_num;
        } else {
            $requestData['OrderId'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        if ($account->getModel() === AbstractGateway::MODEL_NON_SECURE) {
            $requestData['Amount']       = self::amountFormat($order->amount);
            $requestData['CurrencyCode'] = $this->mapCurrency($order->currency);
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($account->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     *
     * @throws Exception
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null, $extraData = null): array
    {

        $inputs = [
            'MerchantNo'        => $account->getClientId(),
            'TerminalNo'        => $account->getTerminalId(),
            'PosnetID'          => $account->getPosNetId(),
            'TransactionType'   => $this->mapTxType($txType),
            'OrderId'           => self::formatOrderId($order->id),
            'Amount'            => (string) self::amountFormat($order->amount),
            'CurrencyCode'      => $this->mapCurrency($order->currency),
            'MerchantReturnURL' => (string) $order->success_url,
            'InstallmentCount'  => $this->mapInstallment($order->installment),
            'Language'          => $this->getLang($account, $order),
            'TxnState'          => 'INITIAL',
            'OpenNewWindow'     => '0',
        ];

        if ($card instanceof AbstractCreditCard) {
            $cardData = [
                'CardNo'         => $card->getNumber(),
                // Kod calisiyor ancak burda bir tutarsizlik var: ExpireDate vs ExpiredDate
                // MacParams icinde ExpireDate olarak geciyor, gonderidigimizde ise ExpiredDate olarak istiyor.
                'ExpiredDate'    => $card->getExpirationDate(),
                'Cvv'            => $card->getCvv(),
                'CardHolderName' => (string) $card->getHolderName(),

                'MacParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'UseOOS'    => '0',
            ];
        } else {
            $cardData = [
                /**
                 * UseOOS alanını 1 yaparak bankanın ortak ödeme sayfasının açılmasını ve
                 * bu ortak ödeme sayfası ile müşterinin kart bilgilerini girmesini sağlatabilir.
                 */
                'UseOOS' => '1',
            ];
        }
        $inputs += $cardData;

        $inputs['Mac'] = $this->crypt->create3DHash($account, $inputs);


        if (isset($order->koiCode) && $order->koiCode > 0) {
            $inputs['UseJokerVadaa'] = '1';
            $inputs['KOICode']       = (string) $order->koiCode;
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * Get amount
     * formats 10.01 to 1001
     *
     * @param float $amount
     *
     * @return int
     */
    public static function amountFormat($amount): int
    {
        return (int) (round($amount, 2) * 100);
    }

    /**
     * Get PrefixedOrderId
     * To check the status of an order or cancel/refund order PosNet
     * - requires the order length to be 24
     * - and order id prefix which is "TDS_" for 3D payments
     *
     * @param string $orderId
     * @param string $accountModel
     *
     * @return string
     */
    public static function mapOrderIdToPrefixedOrderId(string $orderId, string $accountModel): string
    {
        $prefix = self::ORDER_ID_REGULAR_PREFIX;
        if (AbstractGateway::MODEL_3D_SECURE === $accountModel) {
            $prefix = self::ORDER_ID_3D_PREFIX;
        } elseif (AbstractGateway::MODEL_3D_PAY === $accountModel) {
            $prefix = self::ORDER_ID_3D_PAY_PREFIX;
        }

        return $prefix.self::formatOrderId($orderId, self::ORDER_ID_TOTAL_LENGTH - strlen($prefix));
    }


    /**
     * formats order id by adding 0 pad to the left
     *
     * @param string   $orderId
     * @param int|null $padLength
     *
     * @return string
     */
    public static function formatOrderId(string $orderId, int $padLength = null): string
    {
        if (null === $padLength) {
            $padLength = self::ORDER_ID_LENGTH;
        }
        if (strlen($orderId) > $padLength) {
            throw new InvalidArgumentException(sprintf(
            // Banka tarafindan belirlenen kisitlama
                'Saglanan siparis ID\'nin (%s) uzunlugu %d karakter. Siparis ID %d karakterden uzun olamaz!',
                $orderId,
                strlen($orderId),
                $padLength
            ));
        }

        return str_pad($orderId, $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * formats installment
     * @return numeric-string
     */
    public function mapInstallment(?int $installment): string
    {
        if ($installment > 1) {
            return (string) $installment;
        }

        return '0';
    }
}
