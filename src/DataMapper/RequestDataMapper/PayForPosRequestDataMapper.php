<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayForPos Gateway requests
 */
class PayForPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * Kurum kodudur. (Banka tarafından verilir)
     * @var string
     */
    public const MBR_ID = '5';

    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     * @var string
     */
    public const MOTO = '0';

    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';

    /** {@inheritdoc} */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3DModel',
        PosInterface::MODEL_3D_PAY     => '3DPay',
        PosInterface::MODEL_3D_HOST    => '3DHost',
        PosInterface::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'PreAuth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL         => 'Void',
        PosInterface::TX_TYPE_REFUND         => 'Refund',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Refund',
        PosInterface::TX_TYPE_HISTORY        => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS         => 'OrderInquiry',
    ];

    /**
     * {@inheritDoc}
     *
     * @param string $txType kullanilmiyor
     *
     * @return array{RequestGuid: mixed, UserCode: string, UserPass: string, OrderId: mixed, SecureType: string}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $posAccount->getUsername(),
            'UserPass'    => $posAccount->getPassword(),
            'OrderId'     => $order['id'],
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, MOTO: string, OrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, InstallmentCount: string, Lang: string, CardHolderName: string|null, Pan: string, Expiry: string, Cvv2: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MbrId'            => self::MBR_ID,
                'MOTO'             => self::MOTO,
                'OrderId'          => (string) $order['id'],
                'SecureType'       => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'          => $this->mapTxType($txType),
                'PurchAmount'      => (string) $order['amount'],
                'Currency'         => $this->mapCurrency($order['currency']),
                'InstallmentCount' => $this->mapInstallment($order['installment']),
                'Lang'             => $this->getLang($posAccount, $order),
                'CardHolderName'   => $creditCard->getHolderName(),
                'Pan'              => $creditCard->getNumber(),
                'Expiry'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvv2'             => $creditCard->getCvv(),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MbrId'       => self::MBR_ID,
                'OrgOrderId'  => (string) $order['id'],
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'     => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'PurchAmount' => (string) $order['amount'],
                'Currency'    => $this->mapCurrency($order['currency']),
                'Lang'        => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, Lang: string, TxnType: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MbrId'      => self::MBR_ID,
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => 'Inquiry',
                'Lang'       => $this->getLang($posAccount, $order),
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_STATUS),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MbrId'      => self::MBR_ID,
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'Currency'   => $this->mapCurrency($order['currency']),
                'Lang'       => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, SecureType: string, Lang: string, OrgOrderId: string, TxnType: string, PurchAmount: string, Currency: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'MbrId'       => self::MBR_ID,
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Lang'        => $this->getLang($posAccount, $order),
                'OrgOrderId'  => (string) $order['id'],
                'TxnType'     => $this->mapTxType($refundTxType),
                'PurchAmount' => (string) $order['amount'],
                'Currency'    => $this->mapCurrency($order['currency']),
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $requestData = [
            'MbrId'      => self::MBR_ID,
            'SecureType' => 'Report',
            'OrderId'    => $order['id'],
            'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_HISTORY),
            'Lang'       => $this->getLang($posAccount, $order),
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    /**
     * @param array{transaction_date: \DateTimeInterface} $data
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        $requestData = [
            'MbrId'      => self::MBR_ID,
            'SecureType' => 'Report',
            'ReqDate'    => $data['transaction_date']->format('Ymd'),
            'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_HISTORY),
            'Lang'       => $this->getLang($posAccount, $order),
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + ($this->getRequestAccountData($posAccount) + [
                'MbrId' => self::MBR_ID,
            ]);
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
            'MbrId'            => self::MBR_ID,
            'MerchantID'       => $posAccount->getClientId(),
            'UserCode'         => $posAccount->getUsername(),
            'OrderId'          => (string) $order['id'],
            'Lang'             => $this->getLang($posAccount, $order),
            'SecureType'       => $this->secureTypeMappings[$paymentModel],
            'TxnType'          => $this->mapTxType($txType),
            'PurchAmount'      => (string) $order['amount'],
            'InstallmentCount' => $this->mapInstallment($order['installment']),
            'Currency'         => $this->mapCurrency($order['currency']),
            'OkUrl'            => (string) $order['success_url'],
            'FailUrl'          => (string) $order['fail_url'],
            'Rnd'              => $this->crypt->generateRandomString(),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['CardHolderName'] = $creditCard->getHolderName() ?? '';
            $inputs['Pan']            = $creditCard->getNumber();
            $inputs['Expiry']         = $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv2']           = $creditCard->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            PayForPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Hash'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL, //to be filled by the caller
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
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
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return [
            'transaction_date' => $data['transaction_date'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return [
            'id' => $order['id'],
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
     * @param AbstractPosAccount $posAccount
     *
     * @return array{MerchantId: string, UserCode: string, UserPass: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getClientId(),
            'UserCode'   => $posAccount->getUsername(),
            'UserPass'   => $posAccount->getPassword(),
        ];
    }
}
