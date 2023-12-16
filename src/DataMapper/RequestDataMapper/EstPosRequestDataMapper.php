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
 * Creates request data for EstPos Gateway requests
 */
class EstPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'm/y';

    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY      => 'Auth',
        PosInterface::TX_TYPE_PRE_PAY  => 'PreAuth',
        PosInterface::TX_TYPE_POST_PAY => 'PostAuth',
        PosInterface::TX_TYPE_CANCEL   => 'Void',
        PosInterface::TX_TYPE_REFUND   => 'Credit',
        PosInterface::TX_TYPE_STATUS   => 'ORDERSTATUS',
        PosInterface::TX_TYPE_HISTORY  => 'ORDERHISTORY',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $cardTypeMapping = [
        CreditCardInterface::CARD_TYPE_VISA       => '1',
        CreditCardInterface::CARD_TYPE_MASTERCARD => '2',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
        'YEAR'  => 'Y',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE      => '3d',
        PosInterface::MODEL_3D_PAY         => '3d_pay',
        PosInterface::MODEL_3D_PAY_HOSTING => '3d_pay_hosting',
        PosInterface::MODEL_3D_HOST        => '3d_host',
        PosInterface::MODEL_NON_SECURE     => 'regular',
    ];

    /**
     * {@inheritDoc}
     *
     * @param array{md: string, xid: string, eci: string, cavv: string} $responseData
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($account) + [
                'Type'                    => $this->mapTxType($txType),
                'IPAddress'               => (string) $order['ip'],
                'OrderId'                 => (string) $order['id'],
                'Total'                   => (string) $order['amount'],
                'Currency'                => $this->mapCurrency($order['currency']),
                'Taksit'                  => $this->mapInstallment((int) $order['installment']),
                'Number'                  => $responseData['md'],
                'PayerTxnId'              => $responseData['xid'],
                'PayerSecurityLevel'      => $responseData['eci'],
                'PayerAuthenticationCode' => $responseData['cavv'],
                'Mode'                    => 'P',
            ];

        if (isset($order['recurring'])) {
            $requestData += $this->createRecurringData($order['recurring']);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     * @return array{PbOrder?: array{OrderType: string, OrderFrequencyInterval: string, OrderFrequencyCycle: string, TotalNumberPayments: string}, Type: string, IPAddress: string, OrderId: string, Total: string, Currency: string, Taksit: string, Number: string, Expires: string, Cvv2Val: string, Mode: string, Name: string, Password: string, ClientId: string}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($account) + [
                'Type'      => $this->mapTxType($txType),
                'IPAddress' => (string) $order['ip'],
                'OrderId'   => (string) $order['id'],
                'Total'     => (string) $order['amount'],
                'Currency'  => $this->mapCurrency($order['currency']),
                'Taksit'    => $this->mapInstallment((int) $order['installment']),
                'Number'    => $card->getNumber(),
                'Expires'   => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvv2Val'   => $card->getCvv(),
                'Mode'      => 'P',
            ];

        if (isset($order['recurring'])) {
            $requestData += $this->createRecurringData($order['recurring']);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * @return array{Type: string, OrderId: string, Name: string, Password: string, ClientId: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($account) + [
                'Type'    => $this->mapTxType(PosInterface::TX_TYPE_POST_PAY),
                'OrderId' => (string) $order['id'],
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $statusRequestData = $this->getRequestAccountData($account) + [
                'Extra' => [
                    $this->mapTxType(PosInterface::TX_TYPE_STATUS) => 'QUERY',
                ],
            ];

        $order = $this->prepareStatusOrder($order);

        if (isset($order['id'])) {
            $statusRequestData['OrderId'] = $order['id'];
        } elseif (isset($order['recurringId'])) {
            $statusRequestData['Extra']['RECURRINGID'] = $order['recurringId'];
        }

        return $statusRequestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $orderData = [];
        if (isset($order['recurringOrderInstallmentNumber'])) {
            // this method cancels only pending recurring orders, it will not cancel already fulfilled transactions
            $orderData['Extra']['RECORDTYPE'] = 'Order';
            // cancel single installment
            $orderData['Extra']['RECURRINGOPERATION'] = 'Cancel';
            /**
             * the order ids of recurring order installments:
             * 'ORD_ID_1' => '202210121ABC',
             * 'ORD_ID_2' => '202210121ABC-2',
             * 'ORD_ID_3' => '202210121ABC-3',
             * ...
             */
            $orderData['Extra']['RECORDID'] = $order['id'].'-'.$order['recurringOrderInstallmentNumber'];

            return $this->getRequestAccountData($account) + $orderData;
        }

        return $this->getRequestAccountData($account) + [
                'OrderId' => $order['id'],
                'Type'    => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: string, Currency: string, Type: string, Total?: string, Name: string, Password: string, ClientId: string}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = [
            'OrderId'  => (string) $order['id'],
            'Currency' => $this->mapCurrency($order['currency']),
            'Type'     => $this->mapTxType(PosInterface::TX_TYPE_REFUND),
        ];

        if (isset($order['amount'])) {
            $requestData['Total'] = (string) $order['amount'];
        }

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: string, Extra: array<string, string>&array, Name: string, Password: string, ClientId: string}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        $requestData = [
            'OrderId' => (string) $extraData['order_id'], //todo orderId ya da id olarak degistirilecek, Payfor'da orderId, Garanti'de id
            'Extra'   => [
                $this->mapTxType(PosInterface::TX_TYPE_HISTORY) => 'QUERY',
            ],
        ];

        return $this->getRequestAccountData($account) + $requestData;
    }


    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        $data = $this->create3DFormDataCommon($account, $preparedOrder, $paymentModel, $txType, $gatewayURL, $card);

        $event = new Before3DFormHashCalculatedEvent($data['inputs'], $account->getBank(), $txType, $paymentModel);
        $this->eventDispatcher->dispatch($event);
        $data['inputs'] = $event->getRequestData();

        $data['inputs']['hash'] = $this->crypt->create3DHash($account, $data['inputs']);

        return $data;
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_* $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_*       $txType
     *
     * @param array<string, string|int|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    protected function create3DFormDataCommon(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
    {
        $inputs = [
            'clientid'    => $account->getClientId(),
            'storetype'   => $this->secureTypeMappings[$paymentModel],
            'amount'      => (string) $order['amount'],
            'oid'         => (string) $order['id'],
            'okUrl'       => (string) $order['success_url'],
            'failUrl'     => (string) $order['fail_url'],
            'rnd'         => $this->crypt->generateRandomString(),
            'lang'        => $this->getLang($account, $order),
            'currency'    => $this->mapCurrency((string) $order['currency']),
            'taksit'      => $this->mapInstallment((int) $order['installment']),
            'islemtipi'   => $this->mapTxType($txType),
        ];

        if ($card instanceof CreditCardInterface) {
            $inputs['cardType']                        = $this->cardTypeMapping[$card->getType()];
            $inputs['pan']                             = $card->getNumber();
            $inputs['Ecom_Payment_Card_ExpDate_Month'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['Ecom_Payment_Card_ExpDate_Year']  = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['cv2']                             = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
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
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ip'          => $order['ip'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id' => $order['id'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'   => $order['amount'],
        ];
    }

    /**
     * @param AbstractPosAccount $account
     *
     * @return array{Name: string, Password: string, ClientId: string}
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
        ];
    }

    /**
     * @param array{frequency: int, frequencyType: string, installment: int} $recurringData
     *
     * @return array{PbOrder: array{OrderType: string, OrderFrequencyInterval: string, OrderFrequencyCycle: string, TotalNumberPayments: string}}
     */
    private function createRecurringData(array $recurringData): array
    {
        return [
            'PbOrder' => [
                'OrderType'              => '0', // 0: Varsayılan, taksitsiz
                // Periyodik İşlem Frekansı
                'OrderFrequencyInterval' => (string) $recurringData['frequency'],
                // D|M|Y
                'OrderFrequencyCycle'    => $this->mapRecurringFrequency($recurringData['frequencyType']),
                'TotalNumberPayments'    => (string) $recurringData['installment'],
            ],
        ];
    }
}
