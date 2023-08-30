<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class InterPosRequestDataMapper extends AbstractRequestDataMapperCrypt
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
    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3DModel',
        AbstractGateway::MODEL_3D_PAY     => '3DPay',
        AbstractGateway::MODEL_3D_HOST    => '3DHost',
        AbstractGateway::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * Transaction Types
     *
     * {@inheritdoc}
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Auth',
        AbstractGateway::TX_PRE_PAY  => 'PreAuth',
        AbstractGateway::TX_POST_PAY => 'PostAuth',
        AbstractGateway::TX_CANCEL   => 'Void',
        AbstractGateway::TX_REFUND   => 'Refund',
        AbstractGateway::TX_STATUS   => 'StatusHistory',
    ];

    /**
     * {@inheritdoc}
     */
    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => '0',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => '1',
        AbstractCreditCard::CARD_TYPE_AMEX       => '2',
        AbstractCreditCard::CARD_TYPE_TROY       => '3',
    ];

    /**
     * {@inheritDoc}
     *
     * @param array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $responseData
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'TxnType'                 => $this->mapTxType($txType),
                'SecureType'              => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'OrderId'                 => (string) $order->id,
                'PurchAmount'             => (string) $order->amount,
                'Currency'                => $this->mapCurrency($order->currency),
                'InstallmentCount'        => $this->mapInstallment($order->installment),
                'MD'                      => $responseData['MD'],
                'PayerTxnId'              => $responseData['PayerTxnId'],
                'Eci'                     => $responseData['Eci'],
                'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
                'MOTO'                    => self::MOTO,
                'Lang'                    => $this->getLang($account, $order),
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($account) + [
                'TxnType'          => $this->mapTxType($txType),
                'SecureType'       => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'OrderId'          => $order->id,
                'PurchAmount'      => $order->amount,
                'Currency'         => $this->mapCurrency($order->currency),
                'InstallmentCount' => $this->mapInstallment($order->installment),
                'MOTO'             => self::MOTO,
                'Lang'             => $this->getLang($account, $order),
            ];

        if ($card !== null) {
            $requestData['CardType'] = $this->cardTypeMapping[$card->getType()];
            $requestData['Pan']      = $card->getNumber();
            $requestData['Expiry']   = $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $requestData['Cvv2']     = $card->getCvv();
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     * @return array{TxnType: string, SecureType: string, OrderId: null, orgOrderId: mixed, PurchAmount: mixed, Currency: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order, ?AbstractCreditCard $card = null): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'TxnType'     => $this->mapTxType(AbstractGateway::TX_POST_PAY),
                'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'OrderId'     => null,
                'orgOrderId'  => (string) $order->id,
                'PurchAmount' => (string) $order->amount,
                'Currency'    => $this->mapCurrency($order->currency),
                'MOTO'        => self::MOTO,
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($account) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order->id,
                'TxnType'    => $this->mapTxType(AbstractGateway::TX_STATUS),
                'SecureType' => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'Lang'       => $this->getLang($account, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($account) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order->id,
                'TxnType'    => $this->mapTxType(AbstractGateway::TX_CANCEL),
                'SecureType' => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'Lang'       => $this->getLang($account, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, PurchAmount: string, TxnType: string, SecureType: string, Lang: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($account) + [
                'OrderId'     => null,
                'orgOrderId'  => (string) $order->id,
                'PurchAmount' => (string) $order->amount,
                'TxnType'     => $this->mapTxType(AbstractGateway::TX_REFUND),
                'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
                'Lang'        => $this->getLang($account, $order),
                'MOTO'        => self::MOTO,
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        throw new NotImplementedException();
    }


    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $mappedOrder = (array) $order;
        $mappedOrder['installment'] = $this->mapInstallment($order->installment);

        $hash = $this->crypt->create3DHash($account, $mappedOrder, $this->mapTxType($txType));

        $inputs = [
            'ShopCode'         => $account->getClientId(),
            'TxnType'          => $this->mapTxType($txType),
            'SecureType'       => $this->secureTypeMappings[$paymentModel],
            'Hash'             => $hash,
            'PurchAmount'      => $order->amount,
            'OrderId'          => $order->id,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Lang'             => $this->getLang($account, $order),
            'Currency'         => $this->mapCurrency($order->currency),
            'InstallmentCount' => $this->mapInstallment($order->installment),
        ];

        if ($card !== null) {
            $inputs['CardType'] = $this->cardTypeMapping[$card->getType()];
            $inputs['Pan']      = $card->getNumber();
            $inputs['Expiry']   = $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv2']     = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    public function mapInstallment(?int $installment): string
    {
        return $installment > 1 ? (string) $installment : '';
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): object
    {
        return (object) array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? 'TRY',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): object
    {
        return (object) [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? 'TRY',
        ];
    }

    /**
     * @param AbstractPosAccount $account
     *
     * @return array{UserCode: string, UserPass: string, ShopCode: string}
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'UserCode' => $account->getUsername(),
            'UserPass' => $account->getPassword(),
            'ShopCode' => $account->getClientId(),
        ];
    }
}
