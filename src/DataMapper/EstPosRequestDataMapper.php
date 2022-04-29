<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for EstPos Gateway requests
 */
class EstPosRequestDataMapper extends AbstractRequestDataMapper
{
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'm/y';
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /**
     * @inheritdoc
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Auth',
        AbstractGateway::TX_PRE_PAY  => 'PreAuth',
        AbstractGateway::TX_POST_PAY => 'PostAuth',
        AbstractGateway::TX_CANCEL   => 'Void',
        AbstractGateway::TX_REFUND   => 'Credit',
        AbstractGateway::TX_STATUS   => 'ORDERSTATUS',
        AbstractGateway::TX_HISTORY  => 'ORDERHISTORY',
    ];

    protected $cardTypeMapping = [
        AbstractCreditCard::CARD_TYPE_VISA       => '1',
        AbstractCreditCard::CARD_TYPE_MASTERCARD => '2',
    ];

    protected $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
        'YEAR'  => 'Y',
    ];
    //todo store type degerleri ekle

    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3d',
        AbstractGateway::MODEL_3D_PAY     => '3d_pay',
        AbstractGateway::MODEL_3D_HOST    => '3d_host',
        AbstractGateway::MODEL_NON_SECURE => 'regular',
    ];

    /**
     * @inheritdoc
     */
    protected $currencyMappings = [
        'TRY' => 949,
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'JPY' => 392,
        'RUB' => 643,
    ];

    /**
     * @inheritDoc
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        $requestData = [
            'Name'                    => $account->getUsername(),
            'Password'                => $account->getPassword(),
            'ClientId'                => $account->getClientId(),
            'Type'                    => $txType,
            'IPAddress'               => $order->ip ?? null,
            'Email'                   => $order->email,
            'OrderId'                 => $order->id,
            'UserId'                  => $order->user_id ?? null,
            'Total'                   => $order->amount,
            'Currency'                => $order->currency,
            'Taksit'                  => $order->installment,
            'Number'                  => $responseData['md'],
            'Expires'                 => '', //todo
            'Cvv2Val'                 => '', //todo
            'PayerTxnId'              => $responseData['xid'],
            'PayerSecurityLevel'      => $responseData['eci'],
            'PayerAuthenticationCode' => $responseData['cavv'],
            'CardholderPresentCode'   => '13',
            'Mode'                    => 'P',
            'GroupId'                 => '',
            'TransId'                 => '',
        ];

        if ($order->name) {
            $requestData['BillTo'] = [
                'Name' => $order->name,
            ];
        }

        if (isset($order->recurringFrequency)) {
            $requestData['PbOrder'] = [
                'OrderType'              => 0,
                // Periyodik İşlem Frekansı
                'OrderFrequencyInterval' => $order->recurringFrequency,
                //D|M|Y
                'OrderFrequencyCycle'    => $this->mapRecurringFrequency($order->recurringFrequencyType),
                'TotalNumberPayments'    => $order->recurringInstallmentCount,
            ];
        }

        return  $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        return [
            'Name'      => $account->getUsername(),
            'Password'  => $account->getPassword(),
            'ClientId'  => $account->getClientId(),
            'Type'      => $txType,
            'IPAddress' => $order->ip ?? null,
            'Email'     => $order->email,
            'OrderId'   => $order->id,
            'UserId'    => $order->user_id ?? null,
            'Total'     => $order->amount,
            'Currency'  => $order->currency,
            'Taksit'    => $order->installment,
            'CardType'  => $card->getType(), //todo remove
            'Number'    => $card->getNumber(),
            'Expires'   => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'Cvv2Val'   => $card->getCvv(),
            'Mode'      => 'P', //TODO what is this constant for?
            'GroupId'   => '',
            'TransId'   => '',
            'BillTo'    => [
                'Name' => $order->name ?: null,
            ],
        ];
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'Type'     => $this->txTypeMappings[AbstractGateway::TX_POST_PAY],
            'OrderId'  => $order->id,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Extra'    => [
                $this->txTypeMappings[AbstractGateway::TX_STATUS] => 'QUERY',
            ],
        ];
    }

    /**
     * todo
     * @inheritDoc
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Type'     => 'Void',
        ];
    }

    /**
     * @inheritDoc
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        $requestData = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $order->id,
            'Currency' => $order->currency,
            'Type'     => $this->txTypeMappings[AbstractGateway::TX_REFUND],
        ];

        if (isset($order->amount)) {
            $requestData['Total'] = $order->amount;
        }

        return $requestData;
    }

    /**
     * todo
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        $requestData = [
            'Name'     => $account->getUsername(),
            'Password' => $account->getPassword(),
            'ClientId' => $account->getClientId(),
            'OrderId'  => $extraData['order_id'], //todo orderId ya da id olarak degistirilecek, Payfor'da orderId, Garanti'de id
            'Extra'    => [
                $this->txTypeMappings[AbstractGateway::TX_HISTORY] => 'QUERY',
            ],
        ];

        return $requestData;
    }


    /**
     * todo
     * @inheritDoc
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        if (!$order) {
            return [];
        }

        $order->hash = $this->create3DHash($account, $order, $txType);

        $inputs = [
            'clientid'  => $account->getClientId(),
            'storetype' => $account->getModel(),
            'hash'      => $order->hash,
            'firmaadi'  => $order->name,
            'Email'     => $order->email,
            'amount'    => $order->amount,
            'oid'       => $order->id,
            'okUrl'     => $order->success_url,
            'failUrl'   => $order->fail_url,
            'rnd'       => $order->rand,
            'lang'      => $this->getLang($account, $order),
            'currency'  => $order->currency,
        ];

        if ($account->getModel() === AbstractGateway::MODEL_3D_PAY || $account->getModel() === AbstractGateway::MODEL_3D_HOST) {
            $inputs = array_merge($inputs, [
                'islemtipi' => $txType,
                'taksit'    => $order->installment,
            ]);
        }

        if ($card) {
            //todo check card type
            $inputs['cardType'] = $this->cardTypeMapping[$card->getType()];
            $inputs['pan'] = $card->getNumber();
            $inputs['Ecom_Payment_Card_ExpDate_Month'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['Ecom_Payment_Card_ExpDate_Year'] = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['cv2'] = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        $hashData = [];
        if ($account->getModel() === AbstractGateway::MODEL_3D_SECURE) {
            $hashData = [
                $account->getClientId(),
                $order->id,
                $order->amount,
                $order->success_url,
                $order->fail_url,
                $order->rand,
                $account->getStoreKey(),
            ];
        } elseif ($account->getModel() === AbstractGateway::MODEL_3D_PAY || $account->getModel() === AbstractGateway::MODEL_3D_HOST) {
            $hashData = [
                $account->getClientId(),
                $order->id,
                $order->amount,
                $order->success_url,
                $order->fail_url,
                $txType,
                $order->installment,
                $order->rand,
                $account->getStoreKey(),
            ];
        }
        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
