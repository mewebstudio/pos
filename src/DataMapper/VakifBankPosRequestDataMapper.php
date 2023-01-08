<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for VakifBank Gateway requests
 */
class VakifBankPosRequestDataMapper extends AbstractRequestDataMapper
{
    public const CREDIT_CARD_EXP_DATE_LONG_FORMAT = 'Ym';
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'ym';

    /**
     * {@inheritDoc}
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Sale',
        AbstractGateway::TX_PRE_PAY  => 'Auth',
        AbstractGateway::TX_POST_PAY => 'Capture',
        AbstractGateway::TX_CANCEL   => 'Cancel',
        AbstractGateway::TX_REFUND   => 'Refund',
        AbstractGateway::TX_HISTORY  => 'TxnHistory',
        AbstractGateway::TX_STATUS   => 'OrderInquiry',
    ];

    /**
     * {@inheritdoc}
     */
    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => '100',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => '200',
        AbstractCreditCard::CARD_TYPE_TROY       => '300',
        AbstractCreditCard::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * {@inheritdoc}
     */
    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'Day',
        'MONTH' => 'Month',
        'YEAR'  => 'Year',
    ];

    /**
     * @param VakifBankAccount $account
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData, ?AbstractCreditCard $card = null): array
    {
        $requestData = $this->getRequestAccountData($account) + [
            'TransactionType'         => $this->mapTxType($txType),
            'TransactionId'           => $order->id,
            'CurrencyAmount'          => self::amountFormat($order->amount),
            'CurrencyCode'            => $this->mapCurrency($order->currency),
            'CardHoldersName'         => $card->getHolderName(),
            'Cvv'                     => $card->getCvv(),
            'Pan'                     => $card->getNumber(),
            'Expiry'                  => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_LONG_FORMAT),
            'ECI'                     => $responseData['Eci'],
            'CAVV'                    => $responseData['Cavv'],
            'MpiTransactionId'        => $responseData['VerifyEnrollmentRequestId'],
            'OrderId'                 => $order->id,
            'OrderDescription'        => $this->order->description ?? null,
            'ClientIp'                => $order->ip,
            'TransactionDeviceSource' => 0, // ECommerce
        ];

        if ($order->installment) {
            $requestData['NumberOfInstallments'] = $this->mapInstallment($order->installment);
        }

        return $requestData;
    }

    /**
     * @param VakifBankAccount   $account
     * @param object             $order
     * @param AbstractCreditCard $card
     *
     * @return array
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $account, $order, AbstractCreditCard $card): array
    {
        $requestData = [
            'MerchantId'                => $account->getClientId(),
            'MerchantPassword'          => $account->getPassword(),
            'MerchantType'              => $account->getMerchantType(),
            'PurchaseAmount'            => self::amountFormat($order->amount),
            'VerifyEnrollmentRequestId' => $order->rand,
            'Currency'                  => $this->mapCurrency($order->currency),
            'SuccessUrl'                => $order->success_url,
            'FailureUrl'                => $order->fail_url,
            'Pan'                       => $card->getNumber(),
            'ExpiryDate'                => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'BrandName'                 => $this->cardTypeMapping[$card->getType()],
            'IsRecurring'               => 'false',
        ];
        if ($order->installment) {
            $requestData['InstallmentCount'] = $this->mapInstallment($order->installment);
        }
        if (isset($order->extraData)) {
            $requestData['SessionInfo'] = $order->extraData;
        }
        if ($account->isSubBranch()) {
            $requestData['SubMerchantId'] = $account->getSubMerchantId();
        }
        if (isset($order->recurringFrequency)) {
            $requestData['IsRecurring'] = 'true';
            // Periyodik İşlem Frekansı
            $requestData['RecurringFrequency'] = $order->recurringFrequency;
            //Day|Month|Year
            $requestData['RecurringFrequencyType'] = $this->mapRecurringFrequency($order->recurringFrequencyType);
            //recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            $requestData['RecurringInstallmentCount'] = $order->recurringInstallmentCount;
            if (isset($order->recurringEndDate)) {
                //YYYYMMDD
                $requestData['RecurringEndDate'] = $order->recurringEndDate;
            }
        }

        return $requestData;
    }

    /**
     * @param VakifBankAccount        $account
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $requestData = $this->getRequestAccountData($account) + [
            'TransactionType'         => $this->mapTxType($txType),
            'OrderId'                 => $order->id,
            'CurrencyAmount'          => self::amountFormat($order->amount),
            'CurrencyCode'            => $this->mapCurrency($order->currency),
            'ClientIp'                => $order->ip,
            'TransactionDeviceSource' => 0,
        ];

        if ($card) {
            $requestData['Pan']      = $card->getNumber();
            $requestData['Expiry']   = $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_LONG_FORMAT);
            $requestData['Cvv']      = $card->getCvv();
        }

        return $requestData;
    }

    /**
     * @param VakifBankAccount        $account
     * @param                         $order
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        return $this->getRequestAccountData($account) + [
            'TransactionType'        => $this->mapTxType(AbstractGateway::TX_POST_PAY),
            'ReferenceTransactionId' => $order->id,
            'CurrencyAmount'         => self::amountFormat($order->amount),
            'CurrencyCode'           => $this->mapCurrency($order->currency),
            'ClientIp'               => $order->ip,
        ];
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
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => $this->mapTxType(AbstractGateway::TX_CANCEL),
            'ReferenceTransactionId' => $order->id,
            'ClientIp'               => $order->ip,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => $this->mapTxType(AbstractGateway::TX_REFUND),
            'ReferenceTransactionId' => $order->id,
            'ClientIp'               => $order->ip,
            'CurrencyAmount'         => self::amountFormat($order->amount),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param array $extraData
     *
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null, array $extraData = []): array
    {
        $inputs = [
            'PaReq'   => $extraData['PaReq'],
            'TermUrl' => $extraData['TermUrl'],
            'MD'      => $extraData['MD'],
        ];

        return [
            'gateway' => $extraData['ACSUrl'],
            'inputs'  => $inputs,
        ];
    }

    /**
     * Amount Formatter
     *
     * @param float $amount
     *
     * @return string ex: 10.1 => 10.10
     */
    public static function amountFormat(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * {@inheritDoc}
     */
    public function mapInstallment(?int $installment)
    {
        return $installment > 1 ? $installment : 0;
    }

    /**
     * @param VakifBankAccount $account
     *
     * @return array
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'MerchantId' => $account->getClientId(),
            'Password'   => $account->getPassword(),
            'TerminalNo' => $account->getTerminalId(),
        ];
    }
}
