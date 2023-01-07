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

    /** {@inheritdoc} */
    protected $secureTypeMappings = [
        AbstractGateway::MODEL_3D_SECURE  => '3DModel',
        AbstractGateway::MODEL_3D_PAY     => '3DPay',
        AbstractGateway::MODEL_3D_HOST    => '3DHost',
        AbstractGateway::MODEL_NON_SECURE => 'NonSecure',
    ];

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, $order, string $txType, ?AbstractCreditCard $card = null): array
    {
        return $this->getRequestAccountData($account) + [
            'MbrId'            => self::MBR_ID,
            'MOTO'             => self::MOTO,
            'OrderId'          => $order->id,
            'SecureType'       => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'          => $this->mapTxType($txType),
            'PurchAmount'      => $order->amount,
            'Currency'         => $this->mapCurrency($order->currency),
            'InstallmentCount' => $this->mapInstallment($order->installment),
            'Lang'             => $this->getLang($account, $order),
            'CardHolderName'   => $card->getHolderName(),
            'Pan'              => $card->getNumber(),
            'Expiry'           => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'Cvv2'             => $card->getCvv(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, $order, ?AbstractCreditCard $card = null): array
    {
        return $this->getRequestAccountData($account) + [
            'MbrId'       => self::MBR_ID,
            'OrgOrderId'  => $order->id,
            'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'     => $this->mapTxType(AbstractGateway::TX_POST_PAY),
            'PurchAmount' => $order->amount,
            'Currency'    => $this->mapCurrency($order->currency),
            'Lang'        => $this->getLang($account, $order),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, $order): array
    {
        return $this->getRequestAccountData($account) + [
            'MbrId'      => self::MBR_ID,
            'OrgOrderId' => $order->id,
            'SecureType' => 'Inquiry',
            'Lang'       => $this->getLang($account, $order),
            'TxnType'    => $this->mapTxType(AbstractGateway::TX_STATUS),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, $order): array
    {
        return $this->getRequestAccountData($account) + [
            'MbrId'      => self::MBR_ID,
            'OrgOrderId' => $order->id,
            'SecureType' => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'TxnType'    => $this->mapTxType(AbstractGateway::TX_CANCEL),
            'Currency'   => $this->mapCurrency($order->currency),
            'Lang'       => $this->getLang($account, $order),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, $order): array
    {
        return $this->getRequestAccountData($account) + [
            'MbrId'       => self::MBR_ID,
            'SecureType'  => $this->secureTypeMappings[AbstractGateway::MODEL_NON_SECURE],
            'Lang'        => $this->getLang($account, $order),
            'OrgOrderId'  => $order->id,
            'TxnType'     => $this->mapTxType(AbstractGateway::TX_REFUND),
            'PurchAmount' => $order->amount,
            'Currency'    => $this->mapCurrency($order->currency),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, $order, array $extraData = []): array
    {
        $requestData = [
            'MbrId'      => self::MBR_ID,
            'SecureType' => 'Report',
            'TxnType'    => $this->mapTxType(AbstractGateway::TX_HISTORY),
            'Lang'       => $this->getLang($account, $order),
        ];

        if (isset($extraData['orderId'])) {
            $requestData['OrderId'] = $extraData['orderId'];
        } elseif (isset($extraData['reqDate'])) {
            //ReqData YYYYMMDD format
            $requestData['ReqDate'] = $extraData['reqDate'];
        }

        return $this->getRequestAccountData($account) + $requestData;
    }


    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $mappedOrder = (array) $order;
        $mappedOrder['installment'] = $this->mapInstallment($order->installment);
        $hash = $this->crypt->create3DHash($account, $mappedOrder, $this->mapTxType($txType));

        $inputs = [
            'MbrId'            => self::MBR_ID,
            'MerchantID'       => $account->getClientId(),
            'UserCode'         => $account->getUsername(),
            'OrderId'          => $order->id,
            'Lang'             => $this->getLang($account, $order),
            'SecureType'       => $this->secureTypeMappings[$account->getModel()],
            'TxnType'          => $this->mapTxType($txType),
            'PurchAmount'      => $order->amount,
            'InstallmentCount' => $this->mapInstallment($order->installment),
            'Currency'         => $this->mapCurrency($order->currency),
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
     * {@inheritDoc}
     */
    public function mapInstallment(?int $installment)
    {
        return $installment > 1 ? $installment : 0;
    }

    /**
     * @param AbstractPosAccount $account
     *
     * @return array
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
