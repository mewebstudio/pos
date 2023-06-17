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
        AbstractGateway::TX_POST_PAY => 'Capt',
        AbstractGateway::TX_CANCEL   => 'reverse',
        AbstractGateway::TX_REFUND   => 'return',
        AbstractGateway::TX_STATUS   => 'agreement',
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
            'ApiType' => 'JSON',
            'ApiVersion' => self::API_VERSION,
            'MerchantNo' => $account->getClientId(),
            'TerminalNo' => $account->getTerminalId(),
            'PaymentInstrumentType' => 'CARD',
            'IsEncrypted' => 'N',
            'IsTDSecureMerchant' => 'Y',
            'IsMailOrder' => 'N',
            'ThreeDSecureData' => [
                'SecureTransactionId' => $responseData['SecureTransactionId'],
                'CavvData' => $responseData['CAVV'],
                'Eci' => $responseData['ECI'],
                'MdStatus' => (int) $responseData['MdStatus'],
                'MD' => $responseData['MD'],
            ],
            'MACParams' => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
            'Amount' => self::amountFormat($order->amount),
            'CurrencyCode' => self::mapCurrency($order->currency),
            'PointAmount' => 0,
            'OrderId' => self::formatOrderId($order->id),
            'InstallmentCount' => $this->mapInstallment($order->installment),
        ];

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
        $requestData = [
            'mid'                                 => $account->getClientId(),
            'tid'                                 => $account->getTerminalId(),
            'tranDateRequired'                    => '1',
            strtolower($this->mapTxType($txType)) => [
                'orderID'      => self::formatOrderId($order->id),
                'installment'  => $this->mapInstallment($order->installment),
                'amount'       => self::amountFormat($order->amount),
                'currencyCode' => $this->mapCurrency($order->currency),
                'ccno'         => $card->getNumber(),
                'expDate'      => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'cvc'          => $card->getCvv(),
            ],
        ];

        if (isset($order->koiCode) && $order->koiCode > 0) {
            $requestData[strtolower($this->mapTxType($txType))]['koiCode'] = $order->koiCode;
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
        return [
            'mid'                                                      => $account->getClientId(),
            'tid'                                                      => $account->getTerminalId(),
            'tranDateRequired'                                         => '1',
            strtolower($this->mapTxType(AbstractGateway::TX_POST_PAY)) => [
                'hostLogKey'   => $order->ref_ret_num,
                'amount'       => self::amountFormat($order->amount),
                'currencyCode' => $this->mapCurrency($order->currency),
                'installment'  => $this->mapInstallment($order->installment),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        $txType = $this->mapTxType(AbstractGateway::TX_STATUS);

        return [
            'mid'   => $account->getClientId(),
            'tid'   => $account->getTerminalId(),
            $txType => [
                'orderID' => self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel()),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        $txType      = $this->mapTxType(AbstractGateway::TX_CANCEL);
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => [
                'transaction' => 'sale',
            ],
        ];

        if (isset($order->auth_code)) {
            $requestData[$txType]['authCode'] = $order->auth_code;
        }

        //either will work
        if (isset($order->ref_ret_num)) {
            $requestData[$txType]['hostLogKey'] = $order->ref_ret_num;
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $txType      = $this->mapTxType(AbstractGateway::TX_REFUND);
        $requestData = [
            'mid'              => $account->getClientId(),
            'tid'              => $account->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => [
                'amount'       => self::amountFormat($order->amount),
                'currencyCode' => $this->mapCurrency($order->currency),
            ],
        ];

        if (isset($order->ref_ret_num)) {
            $requestData[$txType]['hostLogKey'] = $order->ref_ret_num;
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

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
            'Amount'            => self::amountFormat($order->amount),
            'CurrencyCode'      => $this->mapCurrency($order->currency),
            'MerchantReturnURL' => $order->success_url,
            'InstallmentCount'  => $this->mapInstallment($order->installment),
            'Language'          => $this->getLang($account, $order),
            'TxnState'          => 'INITIAL',
            'OpenNewWindow'     => 0,
        ];

        if ($card instanceof AbstractCreditCard) {
            $cardData = [
                'CardNo'         => $card->getNumber(),
                'ExpiredDate'    => $card->getExpirationDate(),
                'Cvv'            => $card->getCvv(),
                'CardHolderName' => $card->getHolderName(),

                'MacParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'UseOOS'    => 0,
            ];
        } else {
            $cardData = [
                /**
                 * UseOOS alanını 1 yaparak bankanın ortak ödeme sayfasının açılmasını ve
                 * bu ortak ödeme sayfası ile müşterinin kart bilgilerini girmesini sağlatabilir.
                 */
                'UseOOS' => 1,
            ];
        }
        $inputs += $cardData;

        $inputs['Mac'] = $this->crypt->create3DHash($account, $inputs);


        if (isset($order->koiCode) && $order->koiCode > 0) {
            $inputs['UseJokerVadaa'] = '1';
            $inputs['KOICode']       = $order->koiCode;
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
     */
    public function mapInstallment(?int $installment): string
    {
        if ($installment > 1) {
            return (string) $installment;
        }

        return '0';
    }
}
