<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Exception;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for PosNet Gateway requests
 */
class PosNetRequestDataMapper extends AbstractRequestDataMapper
{
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'ym';

    protected const HASH_ALGORITHM = 'sha256';
    protected const HASH_SEPARATOR = ';';

    /**
     * PosNet requires order id with specific length
     */
    private const ORDER_ID_LENGTH = 20;
    /**
     * order Id total length including prefix;
     */
    private const ORDER_ID_TOTAL_LENGTH = 24;

    private const ORDER_ID_3D_PREFIX = 'TDSC';
    private const ORDER_ID_3D_PAY_PREFIX = '';  //?
    private const ORDER_ID_REGULAR_PREFIX = '';  //?

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritDoc
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        return [
            'mid'         => $account->getClientId(),
            'tid'         => $account->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
                'mac'          => $this->create3DHash($account, $order, $txType),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $requestData = [
            'mid'               => $account->getClientId(),
            'tid'               => $account->getTerminalId(),
            'tranDateRequired'  => '1',
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
     * @inheritDoc
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        return [
            'mid'                                                           => $account->getClientId(),
            'tid'                                                           => $account->getTerminalId(),
            'tranDateRequired'                                              => '1',
            strtolower($this->mapTxType(AbstractGateway::TX_POST_PAY)) => [
                'hostLogKey'   => $order->host_ref_num,
                'amount'       => self::amountFormat($order->amount),
                'currencyCode' => $this->mapCurrency($order->currency),
                'installment'  => $this->mapInstallment($order->installment),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
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
     * @inheritDoc
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
        if (isset($order->host_ref_num)) {
            $requestData[$txType]['hostLogKey'] = $order->host_ref_num;
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        return $requestData;
    }

    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
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

        if (isset($order->host_ref_num)) {
            $requestData[$txType]['hostLogKey'] = $order->host_ref_num;
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order->id, $account->getModel());
        }

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
     *
     * @throws Exception
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null, $extraData = null): array
    {
        $inputs = [
            'mid'               => $account->getClientId(),
            'posnetID'          => $account->getPosNetId(),
            'vftCode'           => $account->promotion_code ?? null, //todo bunun account icine veya order icin tasinmasi gerekiyor
            'posnetData'        => $extraData['data1'], //Ödeme bilgilerini içermektedir.
            'posnetData2'       => $extraData['data2'], //Kart bilgileri request içerisinde bulunuyorsa bu alan oluşturulmaktadır
            'digest'            => $extraData['sign'],  //Servis imzası.
            'merchantReturnURL' => $order->success_url,
            'url'               => '', //todo belki kaldirabiliriz
            'lang'              => $this->getLang($account, $order),
        ];

        if (isset($order->koiCode) && $order->koiCode > 0) {
            $inputs['useJokerVadaa'] = 1;
        }

        return [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
    }

    /**
     * @param PosNetAccount      $account
     * @param                    $order
     * @param string             $txType
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $account, $order, string $txType, AbstractCreditCard $card): array
    {
        if (null === $card->getHolderName() && isset($order->name)) {
            $card->setHolderName($order->name);
        }

        return [
            'mid'            => $account->getClientId(),
            'tid'            => $account->getTerminalId(),
            'oosRequestData' => [
                'posnetid'       => $account->getPosNetId(),
                'ccno'           => $card->getNumber(),
                'expDate'        => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'cvc'            => $card->getCvv(),
                'amount'         => self::amountFormat($order->amount),
                'currencyCode'   => $this->mapCurrency($order->currency),
                'installment'    => $this->mapInstallment($order->installment),
                'XID'            => self::formatOrderId($order->id),
                'cardHolderName' => $card->getHolderName(),
                'tranType'       => $this->mapTxType($txType),
            ],
        ];
    }

    /**
     * @param PosNetAccount $account
     * @param               $order
     * @param array         $responseData
     *
     * @return array
     */
    public function create3DResolveMerchantRequestData(AbstractPosAccount $account, $order, array $responseData): array
    {
        return [
            'mid'                    => $account->getClientId(),
            'tid'                    => $account->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'mac'          => $this->create3DHash($account, $order, ''),
            ],
        ];
    }


    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        if ($account->getModel() === AbstractGateway::MODEL_3D_SECURE || $account->getModel() === AbstractGateway::MODEL_3D_PAY) {
            $secondHashData = [
                self::formatOrderId($order->id),
                self::amountFormat($order->amount),
                $this->mapCurrency($order->currency),
                $account->getClientId(),
                $this->createSecurityData($account),
            ];
            $hashStr        = implode(static::HASH_SEPARATOR, $secondHashData);

            return $this->hashString($hashStr);
        }

        return '';
    }

    /**
     * Make Security Data
     *
     * @param PosNetAccount $account
     *
     * @return string
     */
    public function createSecurityData(PosNetAccount $account): string
    {
        $hashData = [
            $account->getStoreKey(),
            $account->getTerminalId(),
        ];
        $hashStr  = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
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
        return round($amount, 2) * 100;
    }

    /**
     * Get PrefixedOrderId
     * To check the status of an order or cancel/refund order Yapikredi
     * - requires the order length to be 24
     * - and order id prefix which is "TDSC" for 3D payments
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

        return str_pad($orderId, $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * formats installment in 00, 02, 06 format
     * @inheritdoc
     */
    public function mapInstallment(?int $installment)
    {
        if ($installment > 1) {
            return str_pad($installment, 2, '0', STR_PAD_LEFT);
        }

        return '00';
    }
}
