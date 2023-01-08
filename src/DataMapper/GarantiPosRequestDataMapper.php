<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for GarantiPos Gateway requests
 */
class GarantiPosRequestDataMapper extends AbstractRequestDataMapperCrypt
{
    public const API_VERSION = 'v0.01';

    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /**
     * {@inheritDoc}
     */
    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3D',
        AbstractGateway::MODEL_3D_PAY     => '3D_PAY',
    ];

    /**
     * {@inheritDoc}
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

    private const MOTO = 'N';

    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
    ];

    /** @var CryptInterface|GarantiPosCrypt|null */
    protected $crypt;

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData);

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
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
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
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType($txType), $card);

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
                'Type'                  => $this->mapTxType($txType),
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
                'Amount'                => self::amountFormat($order->amount),
                'CurrencyCode'          => $this->mapCurrency($order->currency),
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
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType(AbstractGateway::TX_POST_PAY), $card);

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
                'Type'              => $this->mapTxType(AbstractGateway::TX_POST_PAY),
                'Amount'            => self::amountFormat($order->amount),
                'CurrencyCode'      => $this->mapCurrency($order->currency),
                'OriginalRetrefNum' => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType(AbstractGateway::TX_STATUS));

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
                'Type'                  => $this->mapTxType(AbstractGateway::TX_STATUS),
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
                'Amount'                => self::amountFormat($order->amount), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order->currency),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType(AbstractGateway::TX_CANCEL));

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
                'Type'                  => $this->mapTxType(AbstractGateway::TX_CANCEL),
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
                'Amount'                => self::amountFormat($order->amount), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order->currency),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType(AbstractGateway::TX_REFUND));

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
                'Type'                  => $this->mapTxType(AbstractGateway::TX_REFUND),
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
                'Amount'                => self::amountFormat($order->amount), //sabit olarak amount 100 gonderilecek,
                'CurrencyCode'          => $this->mapCurrency($order->currency),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order->ref_ret_num,
            ],
        ];
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        $hashData = [
            'id' => $order->id,
            'amount' => self::amountFormat($order->amount),
        ];
        $hash = $this->crypt->createHash($account, $hashData, $this->mapTxType(AbstractGateway::TX_HISTORY));

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
                'Type'                  => $this->mapTxType(AbstractGateway::TX_HISTORY),
                'InstallmentCnt'        => $this->mapInstallment($order->installment),
                'Amount'                => self::amountFormat($order->amount), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order->currency),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];
    }


    /**
     * @param GarantiPosAccount $account
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $mappedOrder = $this->mapPaymentOrder($order);

        $inputs = [
            'secure3dsecuritylevel' => $this->secureTypeMappings[$account->getModel()],
            'mode'                  => $this->getMode(),
            'apiversion'            => self::API_VERSION,
            'terminalprovuserid'    => $account->getUsername(),
            'terminaluserid'        => $account->getUsername(),
            'terminalmerchantid'    => $account->getClientId(),
            'terminalid'            => $account->getTerminalId(),
            'txntype'               => $this->mapTxType($txType),
            'txnamount'             => $mappedOrder['amount'],
            'txncurrencycode'       => $mappedOrder['currency'],
            'txninstallmentcount'   => $mappedOrder['installment'],
            'orderid'               => $mappedOrder['id'],
            'successurl'            => $mappedOrder['success_url'],
            'errorurl'              => $mappedOrder['fail_url'],
            'customeremailaddress'  => $mappedOrder['email'] ?? null,
            'customeripaddress'     => $mappedOrder['ip'],
        ];

        $inputs['secure3dhash'] = $this->crypt->create3DHash($account, $mappedOrder, $this->mapTxType($txType));

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
     * {@inheritDoc}
     */
    public function mapInstallment(?int $installment)
    {
        return $installment > 1 ? $installment : '';
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
        return intval(round($amount, 2) * 100);
    }

    /**
     * @return string
     */
    private function getMode(): string
    {
        return !$this->isTestMode() ? 'PROD' : 'TEST';
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

    private function mapPaymentOrder($order): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['amount'] = self::amountFormat($mappedOrder['amount']);
        $mappedOrder['currency'] = $this->mapCurrency($mappedOrder['currency']);
        $mappedOrder['installment'] = $this->mapInstallment($mappedOrder['installment']);

        return $mappedOrder;
    }
}
