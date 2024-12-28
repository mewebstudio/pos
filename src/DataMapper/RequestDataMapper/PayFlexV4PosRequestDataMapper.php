<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use DateTimeInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayFlex V4 Gateway requests
 */
class PayFlexV4PosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_LONG_FORMAT = 'Ym';

    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'ym';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capture',
        PosInterface::TX_TYPE_CANCEL         => 'Cancel',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_STATUS         => 'status',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMapping = [
        CreditCardInterface::CARD_TYPE_VISA       => '100',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '200',
        CreditCardInterface::CARD_TYPE_TROY       => '300',
        CreditCardInterface::CARD_TYPE_AMEX       => '400',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMapping = [
        'DAY'   => 'Day',
        'MONTH' => 'Month',
        'YEAR'  => 'Year',
    ];

    /**
     * @param PayFlexAccount                                                      $posAccount
     * @param array{Eci: string, Cavv: string, VerifyEnrollmentRequestId: string} $responseData
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData, ?CreditCardInterface $creditCard = null): array
    {
        if (!$creditCard instanceof \Mews\Pos\Entity\Card\CreditCardInterface) {
            throw new \LogicException('Ödemeyi tamamlamak için kart bilgiler zorunlu!');
        }

        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'TransactionType'         => $this->mapTxType($txType),
                'TransactionId'           => (string) $order['id'],
                'CurrencyAmount'          => $this->formatAmount($order['amount']),
                'CurrencyCode'            => $this->mapCurrency($order['currency']),
                'ECI'                     => $responseData['Eci'],
                'CAVV'                    => $responseData['Cavv'],
                'MpiTransactionId'        => $responseData['VerifyEnrollmentRequestId'],
                'OrderId'                 => (string) $order['id'],
                'ClientIp'                => (string) $order['ip'],
                'TransactionDeviceSource' => '0', // ECommerce
                'CardHoldersName'         => $creditCard->getHolderName(),
                'Cvv'                     => $creditCard->getCvv(),
                'Pan'                     => $creditCard->getNumber(),
                'Expiry'                  => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_LONG_FORMAT),
            ];

        if ($order['installment']) {
            $requestData['NumberOfInstallments'] = $this->mapInstallment($order['installment']);
        }

        return $requestData;
    }

    /**
     * @param PayFlexAccount                       $posAccount
     * @param array<string, int|string|float|null> $order
     * @param CreditCardInterface                  $creditCard
     *
     * @return array
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'MerchantId'                => $posAccount->getClientId(),
            'MerchantPassword'          => $posAccount->getPassword(),
            'MerchantType'              => $posAccount->getMerchantType(),
            'PurchaseAmount'            => $this->formatAmount($order['amount']),
            'VerifyEnrollmentRequestId' => $this->crypt->generateRandomString(),
            'Currency'                  => $this->mapCurrency($order['currency']),
            'SuccessUrl'                => $order['success_url'],
            'FailureUrl'                => $order['fail_url'],
            'Pan'                       => $creditCard->getNumber(),
            'ExpiryDate'                => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'BrandName'                 => $this->cardTypeMapping[$creditCard->getType()],
            'IsRecurring'               => 'false',
        ];
        if ($order['installment']) {
            $requestData['InstallmentCount'] = $this->mapInstallment($order['installment']);
        }

        if ($posAccount->isSubBranch()) {
            $requestData['SubMerchantId'] = $posAccount->getSubMerchantId();
        }

        if (isset($order['recurring'])) {
            return \array_merge($requestData, $this->createRecurringData($order['recurring']));
        }

        return $requestData;
    }

    /**
     * @param PayFlexAccount $posAccount
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TransactionType'         => $this->mapTxType($txType),
                'OrderId'                 => (string) $order['id'],
                'CurrencyAmount'          => $this->formatAmount($order['amount']),
                'CurrencyCode'            => $this->mapCurrency($order['currency']),
                'ClientIp'                => (string) $order['ip'],
                'TransactionDeviceSource' => '0',
                'Pan'                     => $creditCard->getNumber(),
                'Expiry'                  => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_LONG_FORMAT),
                'Cvv'                     => $creditCard->getCvv(),
            ];
    }

    /**
     * @param PayFlexAccount       $posAccount
     * @param array<string, mixed> $order
     *
     * @return array{TransactionType: string,
     *     ReferenceTransactionId: string,
     *     CurrencyAmount: string,
     *     CurrencyCode: string,
     *     ClientIp: string,
     *     MerchantId: string,
     *     Password: string,
     *     TerminalNo: string}
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'ReferenceTransactionId' => (string) $order['id'],
                'CurrencyAmount'         => $this->formatAmount($order['amount']),
                'CurrencyCode'           => $this->mapCurrency($order['currency']),
                'ClientIp'               => (string) $order['ip'],
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MerchantCriteria: array{HostMerchantId: string, MerchantPassword: string}, TransactionCriteria: array{TransactionId: string, OrderId: string, AuthCode: string}}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return [
            'MerchantCriteria'    => [
                'HostMerchantId'   => $posAccount->getClientId(),
                'MerchantPassword' => $posAccount->getPassword(),
            ],
            'TransactionCriteria' => [
                /**
                 * TransactionId alanına sorgulanmak istenen işlemin TransactionId bilgisi yazılmalıdır.
                 * TransactionId ya da OrderId alanlarının biri zorunludur.
                 * Hem TransactionId hem de OrderId gönderilerek yapılan bir sorgulamada,
                 * TransactionId dikkate alınmaktadır.
                 * OrderID ile sorgulamada bu OrderId ile başarılı işlem varsa başarılı işlem, yoksa son gönderilen işlem raporda görüntülenecektir
                 */
                'TransactionId' => (string) ($order['transaction_id'] ?? ''),
                'OrderId'       => (string) $order['id'],
                'AuthCode'      => '',
            ],
        ];
    }

    /**
     * {@inheritDoc}
     * @return array{MerchantId: string, Password: string, TransactionType: string,
     *     ReferenceTransactionId: string, ClientIp: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return [
            'MerchantId'             => $posAccount->getClientId(),
            'Password'               => $posAccount->getPassword(),
            'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
            'ReferenceTransactionId' => (string) $order['transaction_id'],
            'ClientIp'               => (string) $order['ip'],
        ];
    }

    /**
     * {@inheritDoc}
     * @return array{MerchantId: string, Password: string, TransactionType: string, ReferenceTransactionId: string,
     *     ClientIp: string, CurrencyAmount: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return [
            'MerchantId'             => $posAccount->getClientId(),
            'Password'               => $posAccount->getPassword(),
            'TransactionType'        => $this->mapTxType($refundTxType),
            'ReferenceTransactionId' => (string) $order['transaction_id'],
            'ClientIp'               => (string) $order['ip'],
            'CurrencyAmount'         => $this->formatAmount($order['amount']),
        ];
    }

    /**
     * @param PayFlexAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, int|string|float|null>|null                         $order kullanilmiyor
     * @param array{PaReq: string, TermUrl: string, MD: string, ACSUrl: string} $extraData
     *
     * @return array{gateway: string, method: 'POST', inputs: array{PaReq: string, TermUrl: string, MD: string}}
     */
    public function create3DFormData(?AbstractPosAccount $posAccount, ?array $order, ?string $paymentModel, ?string $txType, ?string $gatewayURL, ?CreditCardInterface $creditCard = null, array $extraData = []): array
    {
        $inputs = [
            'PaReq'   => $extraData['PaReq'],
            'TermUrl' => $extraData['TermUrl'],
            'MD'      => $extraData['MD'],
        ];

        return [
            'gateway' => $extraData['ACSUrl'],
            'method'  => 'POST',
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
    protected function formatAmount(float $amount): string
    {
        return \number_format($amount, 2, '.', '');
    }

    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
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
            'amount'      => $order['amount'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ip'       => $order['ip'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return [
            'id'             => $order['id'],
            'transaction_id' => $order['transaction_id'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'transaction_id' => $order['transaction_id'],
            'ip'             => $order['ip'],
            'amount'         => $order['amount'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return [
            'transaction_id' => $order['transaction_id'],
            'ip'             => $order['ip'],
        ];
    }

    /**
     * @inheritDoc
     *
     * @return string
     */
    protected function mapCurrency(string $currency): string
    {
        return (string) $this->currencyMappings[$currency] ?? $currency;
    }

    /**
     * @param PayFlexAccount $posAccount
     *
     * @return array{MerchantId: string, Password: string, TerminalNo: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getClientId(),
            'Password'   => $posAccount->getPassword(),
            'TerminalNo' => $posAccount->getTerminalId(),
        ];
    }

    /**
     * @param array{frequency: int, installment: int, frequencyType: string, recurringFrequency: int, endDate: DateTimeInterface} $recurringData
     *
     * @return array{IsRecurring: 'true', RecurringFrequency: string, RecurringFrequencyType: string, RecurringInstallmentCount: string, RecurringEndDate: string}
     */
    private function createRecurringData(array $recurringData): array
    {
        return [
            'IsRecurring'               => 'true',
            'RecurringFrequency'        => (string) $recurringData['frequency'], // Periyodik İşlem Frekansı
            'RecurringFrequencyType'    => $this->mapRecurringFrequency($recurringData['frequencyType']), // Day|Month|Year
            // recurring işlemin toplamda kaç kere tekrar edeceği bilgisini içerir
            'RecurringInstallmentCount' => (string) $recurringData['installment'],
            /**
             * Bu alandaki tarih, kartın son kullanma tarihinden büyükse ACS sunucusu işlemi reddeder.
             */
            'RecurringEndDate'          => $recurringData['endDate']->format('Ymd'),
        ];
    }
}
