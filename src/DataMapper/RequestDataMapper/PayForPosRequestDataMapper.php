<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
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
        PosInterface::TX_TYPE_PAY_AUTH      => 'Auth',
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => 'PreAuth',
        PosInterface::TX_TYPE_PAY_POST_AUTH => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL        => 'Void',
        PosInterface::TX_TYPE_REFUND        => 'Refund',
        PosInterface::TX_TYPE_HISTORY       => 'TxnHistory',
        PosInterface::TX_TYPE_STATUS        => 'OrderInquiry',
    ];

    /**
     * {@inheritDoc}
     *
     * @param string $txType kullanilmiyor
     *
     * @return array{RequestGuid: mixed, UserCode: string, UserPass: string, OrderId: mixed, SecureType: string}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrderId'     => $order['id'],
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, MOTO: string, OrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, InstallmentCount: string, Lang: string, CardHolderName: string|null, Pan: string, Expiry: string, Cvv2: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'MbrId'            => self::MBR_ID,
                'MOTO'             => self::MOTO,
                'OrderId'          => (string) $order['id'],
                'SecureType'       => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'          => $this->mapTxType($txType),
                'PurchAmount'      => (string) $order['amount'],
                'Currency'         => $this->mapCurrency($order['currency']),
                'InstallmentCount' => $this->mapInstallment($order['installment']),
                'Lang'             => $this->getLang($account, $order),
                'CardHolderName'   => $card->getHolderName(),
                'Pan'              => $card->getNumber(),
                'Expiry'           => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvv2'             => $card->getCvv(),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, PurchAmount: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'MbrId'       => self::MBR_ID,
                'OrgOrderId'  => (string) $order['id'],
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'     => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'PurchAmount' => (string) $order['amount'],
                'Currency'    => $this->mapCurrency($order['currency']),
                'Lang'        => $this->getLang($account, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, Lang: string, TxnType: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($account) + [
                'MbrId'      => self::MBR_ID,
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => 'Inquiry',
                'Lang'       => $this->getLang($account, $order),
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_STATUS),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, OrgOrderId: string, SecureType: string, TxnType: string, Currency: string, Lang: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($account) + [
                'MbrId'      => self::MBR_ID,
                'OrgOrderId' => (string) $order['id'],
                'SecureType' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'Currency'   => $this->mapCurrency($order['currency']),
                'Lang'       => $this->getLang($account, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{MbrId: string, SecureType: string, Lang: string, OrgOrderId: string, TxnType: string, PurchAmount: string, Currency: string, MerchantId: string, UserCode: string, UserPass: string}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($account) + [
                'MbrId'       => self::MBR_ID,
                'SecureType'  => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'Lang'        => $this->getLang($account, $order),
                'OrgOrderId'  => (string) $order['id'],
                'TxnType'     => $this->mapTxType(PosInterface::TX_TYPE_REFUND),
                'PurchAmount' => (string) $order['amount'],
                'Currency'    => $this->mapCurrency($order['currency']),
            ];
    }

    /**
     * @param array{id ?: string, reqDate ?: \DateTimeInterface} $extraData
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        $order = $this->prepareHistoryOrder($order);

        $requestData = [
            'MbrId'      => self::MBR_ID,
            'SecureType' => 'Report',
            'TxnType'    => $this->mapTxType(PosInterface::TX_TYPE_HISTORY),
            'Lang'       => $this->getLang($account, $order),
        ];

        if (isset($extraData['id'])) {
            $requestData['OrderId'] = $extraData['id'];
        } elseif (isset($extraData['reqDate'])) {
            $requestData['ReqDate'] = $extraData['reqDate']->format('Ymd');
        }

        return $this->getRequestAccountData($account) + $requestData;
    }


    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'MbrId'            => self::MBR_ID,
            'MerchantID'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'OrderId'          => (string) $order['id'],
            'Lang'             => $this->getLang($account, $order),
            'SecureType'       => $this->secureTypeMappings[$paymentModel],
            'TxnType'          => $this->mapTxType($txType),
            'PurchAmount'      => (string) $order['amount'],
            'InstallmentCount' => $this->mapInstallment($order['installment']),
            'Currency'         => $this->mapCurrency($order['currency']),
            'OkUrl'            => (string) $order['success_url'],
            'FailUrl'          => (string) $order['fail_url'],
            'Rnd'              => $this->crypt->generateRandomString(),
        ];

        if ($card instanceof CreditCardInterface) {
            $inputs['CardHolderName'] = $card->getHolderName() ?? '';
            $inputs['Pan']            = $card->getNumber();
            $inputs['Expiry']         = $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv2']           = $card->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent($inputs, $account->getBank(), $txType, $paymentModel);
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Hash'] = $this->crypt->create3DHash($account, $inputs);

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
    protected function prepareHistoryOrder(array $order): array
    {
        return [
            //reqDate or order id
            'reqDate' => $order['reqDate'] ?? null,
            'id'      => $order['id'] ?? null,
        ];
    }

    /**
     * @param AbstractPosAccount $account
     *
     * @return array{MerchantId: string, UserCode: string, UserPass: string}
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
        ];
    }
}
