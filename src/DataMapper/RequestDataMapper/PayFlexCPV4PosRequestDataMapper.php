<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayFlex Common Payment V4 Gateway requests
 */
class PayFlexCPV4PosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_LONG_FORMAT = 'Ym';

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
        PosInterface::TX_TYPE_HISTORY        => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS         => 'OrderInquiry',
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
    protected array $langMappings = [
        PosInterface::LANG_TR => 'tr-TR',
        PosInterface::LANG_EN => 'en-US',
    ];

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $posAccount
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData, ?CreditCardInterface $creditCard = null): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PayFlexAccount                                     $posAccount
     * @param array{TransactionId: string, PaymentToken: string} $responseData
     *
     * @return array{HostMerchantId: string, Password: string, TransactionId: string, PaymentToken: string}
     */
    public function create3DPaymentStatusRequestData(AbstractPosAccount $posAccount, array $responseData): array
    {
        return $this->getRequestAccountData($posAccount) + [
                'HostMerchantId' => $posAccount->getClientId(),
                'Password'       => $posAccount->getPassword(),
                'TransactionId'  => $responseData['TransactionId'],
                'PaymentToken'   => $responseData['PaymentToken'],
            ];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param PayFlexAccount                       $posAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param string                               $paymentModel
     * @param CreditCardInterface|null             $creditCard
     *
     * @return array<string, string>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, string $txType, string $paymentModel, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'HostMerchantId'       => $posAccount->getClientId(),
            'MerchantPassword'     => $posAccount->getPassword(),
            'HostTerminalId'       => $posAccount->getTerminalId(),
            'TransactionType'      => $this->mapTxType($txType),
            'AmountCode'           => $this->mapCurrency($order['currency']),
            'Amount'               => $this->formatAmount($order['amount']),
            'OrderID'              => (string) $order['id'],
            'IsSecure'             => 'true', // Işlemin 3D yapılıp yapılmayacağına dair flag, alabileceği değerler: 'true', 'false'
            /**
             * 3D Programına Dahil Olmayan Kartlar ile İşlem Yapma Flagi: "3D İşlem Flagi" (IsSecure) "true" gönderilmiş
             * işlemler için bir alt seçenektir. Kart sahibi "3D Secure" programına dahil değilse Ortak Ödemenin işlemi
             * Sanal Pos'a gönderip göndermeyeceğini belirtir. "true" gönderilmesi durumunda kart sahibi
             * 3D Secure programına dahil olmasa bile işlemi Sanal Pos'a gönderecektir.
             * Bu tür işlemler "Half Secure" olarak işaretlenecektir.
             */
            'AllowNotEnrolledCard' => 'false',
            'SuccessUrl'           => (string) $order['success_url'],
            'FailUrl'              => (string) $order['fail_url'],
            'RequestLanguage'      => $this->getLang($posAccount, $order),
            /**
             * Bu alanda gönderilecek değer kart hamili
             * ektresinde işlem açıklamasında çıkacaktır.
             * (Abone no vb. bilgiler gönderilebilir)
             */
            'Extract'              => '',
            /**
             * Uye işyeri tarafından işleme ait ek bilgiler varsa CustomItems alanında gönderilir.
             * İçeriğinde "name" ve "value" attirbutelarını barındırır.
             * Örnek: İsim1:Değer1 İsim2:Değer2 İsim3:Değer3
             */
            'CustomItems'          => '',
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $requestData += [
                'BrandNumber'     => $this->cardTypeMapping[$creditCard->getType()],
                'CVV'             => $creditCard->getCvv(),
                'PAN'             => $creditCard->getNumber(),
                'ExpireMonth'     => $creditCard->getExpireMonth(),
                'ExpireYear'      => $creditCard->getExpireYear(),
                'CardHoldersName' => (string) $creditCard->getHolderName(),
            ];
        }

        if ($order['installment']) {
            $requestData['InstallmentCount'] = $this->mapInstallment($order['installment']);
        }

        $requestData['HashedData'] = $this->crypt->create3DHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $posAccount
     *
     * @return array<string, string>
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
     * @param PayFlexAccount                       $posAccount
     * @param array<string, int|string|float|null> $order
     *
     * @return array{TransactionType: string, ReferenceTransactionId: string,
     *     CurrencyAmount: string, CurrencyCode: string, ClientIp: string,
     *     MerchantId: string, Password: string}
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
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $posAccount
     *
     * @return array{MerchantId: string, Password: string, TransactionType: string, ReferenceTransactionId: string,
     *     ClientIp: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TransactionType'        => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'ReferenceTransactionId' => (string) $order['transaction_id'],
                'ClientIp'               => (string) $order['ip'],
            ];
    }

    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $posAccount
     *
     * @return array{MerchantId: string, Password: string, TransactionType: string, ReferenceTransactionId: string,
     *     ClientIp: string, CurrencyAmount: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TransactionType'        => $this->mapTxType($refundTxType),
                'ReferenceTransactionId' => (string) $order['transaction_id'],
                'ClientIp'               => (string) $order['ip'],
                'CurrencyAmount'         => $this->formatAmount($order['amount']),
            ];
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
     * @param array<string, int|string|float|null>|null             $order kullanilmiyor
     * @param array{CommonPaymentUrl: string, PaymentToken: string} $extraData
     *
     * @return array{gateway: string, method: 'GET', inputs: array{Ptkn: string}}
     */
    public function create3DFormData(
        ?AbstractPosAccount  $posAccount,
        ?array               $order,
        ?string              $paymentModel,
        ?string              $txType,
        ?string              $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        array                $extraData = []): array
    {
        return [
            'gateway' => $extraData['CommonPaymentUrl'],
            'method'  => 'GET',
            'inputs'  => [
                'Ptkn' => $extraData['PaymentToken'],
            ],
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
     *
     * @return string
     */
    protected function mapCurrency(string $currency): string
    {
        return (string) $this->currencyMappings[$currency] ?? $currency;
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
     * @param PayFlexAccount $posAccount
     *
     * @return array{MerchantId: string, Password: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getClientId(),
            'Password'   => $posAccount->getPassword(),
        ];
    }
}
