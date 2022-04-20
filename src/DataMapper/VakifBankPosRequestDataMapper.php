<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Exception;
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
     * @inheritdoc
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

    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => '100',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => '200',
        AbstractCreditCard::CARD_TYPE_TROY       => '300',
        AbstractCreditCard::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * Currency mapping
     *
     * @var array
     */
    protected $currencyMappings = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'Day',
        'MONTH' => 'Month',
        'YEAR'  => 'Year',
    ];

    /**
     * @param VakifBankAccount        $account
     * @param                         $order
     * @param string                  $txType
     * @param array                   $responseData
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData, ?AbstractCreditCard $card = null): array
    {
        $requestData = [
            'MerchantId'              => $account->getClientId(),
            'Password'                => $account->getPassword(),
            'TerminalNo'              => $account->getTerminalId(),
            'TransactionType'         => $txType,
            'TransactionId'           => $order->id,
            'CurrencyAmount'          => self::amountFormat($order->amount),
            'CurrencyCode'            => $order->currency,
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
            $requestData['NumberOfInstallments'] = $order->installment;
        }

        return $requestData;
    }

    /**
     * @param VakifBankAccount   $account
     * @param                    $order
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
            'Currency'                  => $order->currency,
            'SuccessUrl'                => $order->success_url,
            'FailureUrl'                => $order->fail_url,
            'Pan'                       => $card->getNumber(),
            'ExpiryDate'                => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'BrandName'                 => $this->cardTypeMapping[$card->getType()],
            'IsRecurring'               => 'false',
        ];
        if ($order->installment) {
            $requestData['InstallmentCount'] = $order->installment;
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
     * @param                         $order
     * @param string                  $txType
     * @param AbstractCreditCard|null $card
     *
     * @return array
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $requestData = [
            'MerchantId'              => $account->getClientId(),
            'Password'                => $account->getPassword(),
            'TerminalNo'              => $account->getTerminalId(),
            'TransactionType'         => $txType,
            'OrderId'                 => $order->id,
            'CurrencyAmount'          => self::amountFormat($order->amount),
            'CurrencyCode'            => $order->currency,
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
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TerminalNo'             => $account->getTerminalId(),
            'TransactionType'        => $this->txTypeMappings[AbstractGateway::TX_POST_PAY],
            'ReferenceTransactionId' => $order->id,
            'CurrencyAmount'         => self::amountFormat($order->amount),
            'CurrencyCode'           => $order->currency,
            'ClientIp'               => $order->ip,
        ];
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
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => $this->txTypeMappings[AbstractGateway::TX_CANCEL],
            'ReferenceTransactionId' => $order->id,
            'ClientIp'               => $order->ip,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MerchantId'             => $account->getClientId(),
            'Password'               => $account->getPassword(),
            'TransactionType'        => $this->txTypeMappings[AbstractGateway::TX_REFUND],
            'ReferenceTransactionId' => $order->id,
            'ClientIp'               => $order->ip,
            'CurrencyAmount'         => self::amountFormat($order->amount),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
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
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function create3DFormDataFromEnrollmentResponse(array $data): array
    {
        $response = $data['Message']['VERes'];
        /**
         * Status values:
         * Y:Kart 3-D Secure programına dâhil
         * N:Kart 3-D Secure programına dâhil değil
         * U:İşlem gerçekleştirilemiyor
         * E:Hata durumu
         */
        if ('E' === $response['Status']) {
            throw new Exception($data['ErrorMessage'], $data['MessageErrorCode']);
        }
        if ('N' === $response['Status']) {
            // todo devam half secure olarak devam et yada satisi iptal et.
            throw new Exception('Kart 3-D Secure programına dâhil değil');
        }
        if ('U' === $response['Status']) {
            throw new Exception('İşlem gerçekleştirilemiyor');
        }

        $inputs = [
            'PaReq'   => $response['PaReq'],
            'TermUrl' => $response['TermUrl'],
            'MD'      => $response['MD'],
        ];

        return [
            'gateway' => $response['ACSUrl'],
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        throw new NotImplementedException();
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
}
