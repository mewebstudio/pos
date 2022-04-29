<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * Creates request data for PayForPos Gateway requests
 */
class PayForPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * Kurum kodudur. (Banka tarafından verilir)
     */
    const MBR_ID = '5';

    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     */
    const MOTO = '0';

    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';

    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3DModel',
        AbstractGateway::MODEL_3D_PAY     => '3DPay',
        AbstractGateway::MODEL_3D_HOST    => '3DHost',
        AbstractGateway::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * @inheritdoc
     */
    protected $txTypeMappings = [
        AbstractGateway::TX_PAY      => 'Auth',
        AbstractGateway::TX_PRE_PAY  => 'PreAuth',
        AbstractGateway::TX_POST_PAY => 'PostAuth',
        AbstractGateway::TX_CANCEL   => 'Void',
        AbstractGateway::TX_REFUND   => 'Refund',
        AbstractGateway::TX_HISTORY  => 'TxnHistory',
        AbstractGateway::TX_STATUS   => 'OrderInquiry',
    ];

    /**
     * @inheritDoc
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, $order, string $txType, array $responseData): array
    {
        return [
            'RequestGuid' => $responseData['RequestGuid'],
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrderId'     => $order->id,
            'SecureType'  => '3DModelPayment',
        ];
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        return [
            'MbrId'            => self::MBR_ID,
            'MerchantId'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'UserPass'         => $account->getPassword(),
            'MOTO'             => self::MOTO,
            'OrderId'          => $order->id,
            'SecureType'       => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'          => $txType,
            'PurchAmount'      => $order->amount,
            'Currency'         => $order->currency,
            'InstallmentCount' => $order->installment,
            'Lang'             => $this->getLang($account, $order),
            'CardHolderName'   => $card->getHolderName(),
            'Pan'              => $card->getNumber(),
            'Expiry'           => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'Cvv2'             => $card->getCvv(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        return  [
            'MbrId'       => self::MBR_ID,
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'OrgOrderId'  => $order->id,
            'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'     => $this->txTypeMappings[AbstractGateway::TX_POST_PAY],
            'PurchAmount' => $order->amount,
            'Currency'    => $order->currency,
            'Lang'        => $this->getLang($account, $order),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'      => self::MBR_ID,
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order->id,
            'SecureType' => 'Inquiry',
            'Lang'       => $this->getLang($account, $order),
            'TxnType'    => $this->txTypeMappings[AbstractGateway::TX_STATUS],
        ];
    }

    /**
     * @inheritDoc
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'      => self::MBR_ID,
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'OrgOrderId' => $order->id,
            'SecureType' => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'    => $this->txTypeMappings[AbstractGateway::TX_CANCEL],
            'Currency'   => $order->currency,
            'Lang'       => $this->getLang($account, $order),
        ];
    }

    /**
     * @inheritDoc
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        return [
            'MbrId'       => self::MBR_ID,
            'MerchantId'  => $account->getClientId(),
            'UserCode'    => $account->getUsername(),
            'UserPass'    => $account->getPassword(),
            'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'Lang'        => $this->getLang($account, $order),
            'OrgOrderId'  => $order->id,
            'TxnType'     => $this->txTypeMappings[AbstractGateway::TX_REFUND],
            'PurchAmount' => $order->amount,
            'Currency'    => $order->currency,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        $requestData = [
            'MbrId'      => self::MBR_ID,
            'MerchantId' => $account->getClientId(),
            'UserCode'   => $account->getUsername(),
            'UserPass'   => $account->getPassword(),
            'SecureType' => 'Report',
            'TxnType'    => $this->txTypeMappings[AbstractGateway::TX_HISTORY],
            'Lang'       => $this->getLang($account, $order),
        ];

        if (isset($extraData['orderId'])) {
            $requestData['OrderId'] = $extraData['orderId'];
        } elseif (isset($extraData['reqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $extraData['reqDate'];
        }

        return $requestData;
    }


    /**
     * @inheritDoc
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $hash = $this->create3DHash($account, $order, $txType);

        $inputs = [
            'MbrId'            => self::MBR_ID,
            'MerchantID'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'OrderId'          => $order->id,
            'Lang'             => $this->getLang($account, $order),
            'SecureType'       => $this->secureTypeMappings[$account->getModel()],
            'TxnType'          => $txType,
            'PurchAmount'      => $order->amount,
            'InstallmentCount' => $order->installment,
            'Currency'         => $order->currency,
            'OkUrl'            => $order->success_url,
            'FailUrl'          => $order->fail_url,
            'Rnd'              => $order->rand,
            'Hash'             => $hash,
        ];

        if ($card) {
            $inputs['CardHolderName'] = $card->getHolderName();
            $inputs['Pan'] = $card->getNumber();
            $inputs['Expiry'] = $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv2'] = $card->getCvv();
        }

        return [
            'gateway' => $gatewayURL, //to be filled by the caller
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        $hashData = [
            self::MBR_ID,
            $order->id,
            $order->amount,
            $order->success_url,
            $order->fail_url,
            $txType,
            $order->installment,
            $order->rand,
            $account->getStoreKey(),
        ];
        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
