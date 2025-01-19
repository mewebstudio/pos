<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class InterPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';

    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     * @var string
     */
    protected const MOTO = '0';

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3DModel',
        PosInterface::MODEL_3D_PAY     => '3DPay',
        PosInterface::MODEL_3D_HOST    => '3DHost',
        PosInterface::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * Transaction Types
     *
     * {@inheritdoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'PreAuth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL         => 'Void',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_STATUS         => 'StatusHistory',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMapping = [
        CreditCardInterface::CARD_TYPE_VISA       => '0',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '1',
        CreditCardInterface::CARD_TYPE_AMEX       => '2',
        CreditCardInterface::CARD_TYPE_TROY       => '3',
    ];

    /**
     *  TODO tekrarlanan odemeler icin daha fazla bilgi lazim, Deniz bank dokumantasyonunda hic bir aciklama yok
     *  ornek kodlarda ise sadece bu alttaki 2 veriyi gondermis.
     * 'MaturityPeriod' => 1,
     * 'PaymentFrequency' => 2,
     *
     * {@inheritDoc}
     *
     * @param array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $responseData
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'                 => $this->mapTxType($txType),
                'SecureType'              => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'OrderId'                 => (string) $order['id'],
                'PurchAmount'             => (string) $order['amount'],
                'Currency'                => $this->mapCurrency($order['currency']),
                'InstallmentCount'        => $this->mapInstallment($order['installment']),
                'MD'                      => $responseData['MD'],
                'PayerTxnId'              => $responseData['PayerTxnId'],
                'Eci'                     => $responseData['Eci'],
                'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
                'MOTO'                    => self::MOTO,
                'Lang'                    => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'          => $this->mapTxType($txType),
                'SecureType'       => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'OrderId'          => $order['id'],
                'PurchAmount'      => $order['amount'],
                'Currency'         => $this->mapCurrency($order['currency']),
                'InstallmentCount' => $this->mapInstallment((int) $order['installment']),
                'MOTO'             => self::MOTO,
                'Lang'             => $this->getLang($posAccount, $order),
                'CardType'         => $this->cardTypeMapping[$creditCard->getType()],
                'Pan'              => $creditCard->getNumber(),
                'Expiry'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvv2'             => $creditCard->getCvv(),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{TxnType: string, SecureType: string, OrderId: null, orgOrderId: mixed, PurchAmount: mixed, Currency: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'     => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'OrderId'     => null,
                'orgOrderId'  => (string) $order['id'],
                'PurchAmount' => (string) $order['amount'],
                'Currency'    => $this->mapCurrency($order['currency']),
                'MOTO'        => self::MOTO,
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order['id'],
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_STATUS),
                'SecureType' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Lang'       => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order['id'],
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'SecureType' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Lang'       => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, PurchAmount: string, TxnType: string, SecureType: string, Lang: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'     => null,
                'orgOrderId'  => (string) $order['id'],
                'PurchAmount' => (string) $order['amount'],
                'TxnType'     => $this->mapTxType($refundTxType),
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Lang'        => $this->getLang($posAccount, $order),
                'MOTO'        => self::MOTO,
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
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'ShopCode'         => $posAccount->getClientId(),
            'TxnType'          => $this->mapTxType($txType),
            'SecureType'       => $this->secureTypeMappings[$paymentModel],
            'PurchAmount'      => $order['amount'],
            'OrderId'          => $order['id'],
            'OkUrl'            => $order['success_url'],
            'FailUrl'          => $order['fail_url'],
            'Rnd'              => $this->crypt->generateRandomString(),
            'Lang'             => $this->getLang($posAccount, $order),
            'Currency'         => $this->mapCurrency($order['currency']),
            'InstallmentCount' => $this->mapInstallment((int) $order['installment']),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['CardType'] = $this->cardTypeMapping[$creditCard->getType()];
            $inputs['Pan']      = $creditCard->getNumber();
            $inputs['Expiry']   = $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv2']     = $creditCard->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            InterPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Hash'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    /**
     * 0 => ''
     * 1 => ''
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '';
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
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     *
     * @return array{UserCode: string, UserPass: string, ShopCode: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'UserCode' => $posAccount->getUsername(),
            'UserPass' => $posAccount->getPassword(),
            'ShopCode' => $posAccount->getClientId(),
        ];
    }
}
