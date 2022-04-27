<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for GarantiPos Gateway requests
 */
class GarantiPosRequestDataMapper extends AbstractRequestDataMapper
{
    public const API_VERSION = 'v0.01';

    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3D',
        AbstractGateway::MODEL_3D_PAY     => '3D_PAY',
        AbstractGateway::MODEL_3D_HOST    => null, //todo
        AbstractGateway::MODEL_NON_SECURE => null,
    ];


    /**
     * @inheritdoc
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'sales',
        AbstractGateway::TX_PRE_PAY  => 'preauth',
        AbstractGateway::TX_POST_PAY => 'postauth',
        AbstractGateway::TX_CANCEL   => 'void',
        AbstractGateway::TX_REFUND   => 'refund',
        AbstractGateway::TX_HISTORY  => 'orderhistoryinq',
        AbstractGateway::TX_STATUS   => 'orderinq',
    ];

    /**
     * @inheritdoc
     */
    protected $currencyMappings = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    private const MOTO = 'N';

    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
    ];

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $hash = $this->createHash($account, $order, $txType);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash),
            'Customer'    => [
                'IPAddress'    => $responseData['customeripaddress'],
                'EmailAddress' => $responseData['customeremailaddress'],
            ],
            'Order'       => [
                'OrderID'     => $responseData['orderid'],
                'AddressList' => $this->getOrderAddressData($order),
            ],
            'Transaction' => [
                'Type'                  => $responseData['txntype'],
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $responseData['txnamount'],
                'CurrencyCode'          => $responseData['txncurrencycode'],
                'CardholderPresentCode' => '13', //13 for 3D secure payment
                'MotoInd'               => self::MOTO,
                'Secure3D'              => [
                    'AuthenticationCode' => $responseData['cavv'],
                    'SecurityLevel'      => $responseData['eci'],
                    'TxnID'              => $responseData['xid'],
                    'Md'                 => $responseData['md'],
                ],
            ],
        ];

        if (isset($order->recurringInstallmentCount)) {
            $result['Recurring'] = $this->createRecurringData($order);
        }

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $hash = $this->createHash($account, $order, $txType, $card);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash),
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Card'        => $this->getCardData($card),
            'Order'       => [
                'OrderID'     => $order->id,
                'AddressList' => $this->getOrderAddressData($order),
            ],
            'Transaction' => [
                'Type'                  => $txType,
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        if (isset($order->recurringInstallmentCount)) {
            $result['Recurring'] = $this->createRecurringData($order);
        }

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        $hash = $this->createHash($account, $order, $this->txTypeMappings[AbstractGateway::TX_POST_PAY], $card);

        return [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash),
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'              => $this->txTypeMappings[AbstractGateway::TX_POST_PAY],
                'Amount'            => $order->amount,
                'CurrencyCode'      => $order->currency,
                'OriginalRetrefNum' => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        $hash = $this->createHash($account, $order, $this->txTypeMappings[AbstractGateway::TX_STATUS]);

        return [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash),
            'Customer'    => [
               'IPAddress'    => $order->ip ?? '',
               'EmailAddress' => $order->email ?? '',
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => $this->txTypeMappings[AbstractGateway::TX_STATUS],
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        $hash = $this->createHash($account, $order, $this->txTypeMappings[AbstractGateway::TX_CANCEL]);

        return [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash, true),
            'Customer'    => [
                'IPAddress'    => $order->ip ?? '',
                'EmailAddress' => $order->email ?? '',
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => $this->txTypeMappings[AbstractGateway::TX_CANCEL],
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount, //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $txType = $this->txTypeMappings[AbstractGateway::TX_REFUND];
        $hash = $this->createHash($account, $order, $txType);

        return [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash, true),
            'Customer'    => [
                'IPAddress'    => $order->ip,
                'EmailAddress' => $order->email,
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => $txType,
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount,
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        $txType = $this->txTypeMappings[AbstractGateway::TX_HISTORY];
        $hash = $this->createHash($account, $order, $txType);

        return [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, $hash),
            'Customer'    => [
               'IPAddress'    => $order->ip ?? '',
               'EmailAddress' => $order->email ?? '',
            ],
            'Order'       => [
                'OrderID' => $order->id,
            ],
            'Transaction' => [
                'Type'                  => $txType,
                'InstallmentCnt'        => $order->installment,
                'Amount'                => $order->amount, //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $order->currency,
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];
    }


    /**
     * @inheritDoc
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $hashData = $this->create3DHash($account, $order, $txType);

        $inputs = [
            'secure3dsecuritylevel' => $this->secureTypeMappings[$account->getModel()],
            'mode'                  => $this->getMode(),
            'apiversion'            => self::API_VERSION,
            'terminalprovuserid'    => $account->getUsername(),
            'terminaluserid'        => $account->getUsername(),
            'terminalmerchantid'    => $account->getClientId(),
            'terminalid'            => $account->getTerminalId(),
            'txntype'               => $txType,
            'txnamount'             => $order->amount,
            'txncurrencycode'       => $order->currency,
            'txninstallmentcount'   => $order->installment,
            'orderid'               => $order->id,
            'successurl'            => $order->success_url,
            'errorurl'              => $order->fail_url,
            'customeremailaddress'  => $order->email ?? null,
            'customeripaddress'     => $order->ip,
            'secure3dhash'          => $hashData,
        ];

        if ($card) {
            $inputs['cardnumber'] = $card->getNumber();
            $inputs['cardexpiredatemonth'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['cardexpiredateyear'] = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['cardcvv2'] = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        $map = [
            $account->getTerminalId(),
            $order->id,
            $order->amount,
            $order->success_url,
            $order->fail_url,
            $txType,
            $order->installment,
            $account->getStoreKey(),
            $this->createSecurityData($account, $txType),
        ];

        return $this->hashString(implode(static::HASH_SEPARATOR, $map));
    }

    /**
     * Make Hash Data
     *
     * @param GarantiPosAccount       $account
     * @param                         $order
     * @param string                  $txType
     * @param AbstractCreditCard|null $card
     *
     * @return string
     */
    public function createHash(GarantiPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): string
    {
        $map = [
            $order->id,
            $account->getTerminalId(),
            isset($card) ? $card->getNumber() : null,
            $order->amount,
            $this->createSecurityData($account, $txType),
        ];

        return $this->hashString(implode(static::HASH_SEPARATOR, $map));
    }

    /**
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
     * @param float $amount
     *
     * @return int
     */
    public static function amountFormat($amount): int
    {
        return round($amount, 2) * 100;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function hashString(string $str): string
    {
        return strtoupper(hash(static::HASH_ALGORITHM, $str));
    }

    /**
     * @return string
     */
    private function getMode(): string
    {
        return !$this->isTestMode() ? 'PROD' : 'TEST';
    }

    /**
     * Make Security Data
     *
     * @param GarantiPosAccount $account
     * @param string            $txType
     *
     * @return string
     */
    private function createSecurityData(AbstractPosAccount $account, string $txType): string
    {
        if ($txType === $this->txTypeMappings[AbstractGateway::TX_REFUND] || $txType === $this->txTypeMappings[AbstractGateway::TX_CANCEL]) {
            $password = $account->getRefundPassword();
        } else {
            $password = $account->getPassword();
        }

        $map = [
            $password,
            str_pad((int) $account->getTerminalId(), 9, 0, STR_PAD_LEFT),
        ];

        return $this->hashString(implode(static::HASH_SEPARATOR, $map));
    }

    /**
     * @param GarantiPosAccount $account
     * @param string            $hash
     * @param bool              $isRefund
     *
     * @return array
     */
    private function getTerminalData(AbstractPosAccount $account, string $hash, bool $isRefund = false): array
    {
        return [
            'ProvUserID' => $isRefund ? $account->getRefundUsername() : $account->getUsername(),
            'UserID'     => $isRefund ? $account->getRefundUsername() : $account->getUsername(),
            'HashData'   => $hash,
            'ID'         => $account->getTerminalId(),
            'MerchantID' => $account->getClientId(),
        ];
    }

    /**
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    private function getCardData(?AbstractCreditCard $card = null): array
    {
        if ($card) {
            return [
                'Number'     => $card->getNumber(),
                'ExpireDate' => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'CVV2'       => $card->getCvv(),
            ];
        }

        return [
            'Number'     => '',
            'ExpireDate' => '',
            'CVV2'       => '',
        ];
    }

    /**
     * @param $order
     *
     * @return array
     */
    private function getOrderAddressData($order): array
    {
        return [
            'Address' => [
                'Type'        => 'B', //S - shipping, B - billing
                'Name'        => $order->name,
                'LastName'    => '',
                'Company'     => '',
                'Text'        => '',
                'District'    => '',
                'City'        => '',
                'PostalCode'  => '',
                'Country'     => '',
                'PhoneNumber' => '',
            ],
        ];
    }

    /**
     * ornek:
     * <Recurring>
     *   <Type>G veya R</Type> R:Sabit Tutarli   G:Degisken Tutar
     *   <TotalPaymentNum></TotalPaymentNum>
     *   <FrequencyType>M , W , D </FrequencyType> Monthly, weekly, daily
     *   <FrequencyInterval></FrequencyInterval>
     *   <StartDate></StartDate>
     *   <PaymentList>
     *       <Payment>
     *           <PaymentNum></PaymentNum>
     *           <Amount></Amount>
     *           <DueDate></DueDate> YYYYMMDD
     *       </Payment>
     *   </PaymentList>
     * </Recurring>
     * @param $order
     *
     * @return array
     */
    private function createRecurringData($order): array
    {
        return [
            'TotalPaymentNum' => $order->recurringInstallmentCount, //kac kere tekrarlanacak
            'FrequencyType' => $this->mapRecurringFrequency($order->recurringFrequencyType), //Monthly, weekly, daily
            'FrequencyInterval' => $order->recurringFrequency,
            'Type' => $order->recurringType ?? 'R', //R:Sabit Tutarli   G:Degisken Tuta
            'StartDate' => $order->startDate ?? '',
        ];
    }
}
