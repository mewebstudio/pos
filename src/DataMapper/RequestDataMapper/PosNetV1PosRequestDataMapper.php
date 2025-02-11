<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use InvalidArgumentException;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PosNetV1Pos Gateway requests
 */
class PosNetV1PosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = 'V100';

    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'ym';

    /**
     * PosNet requires order id with specific length
     * @var int
     */
    private const ORDER_ID_LENGTH = 20;

    /**
     * order id total length including prefix;
     * @var int
     */
    private const ORDER_ID_TOTAL_LENGTH = 24;

    /** @var string */
    private const ORDER_ID_3D_PREFIX = 'TDS_';

    /** @var string */
    private const ORDER_ID_3D_PAY_PREFIX = ''; //?

    /** @var string */
    private const ORDER_ID_REGULAR_PREFIX = ''; //?

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capture',
        PosInterface::TX_TYPE_CANCEL         => 'Reverse',
        PosInterface::TX_TYPE_REFUND         => 'Return',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'Return',
        PosInterface::TX_TYPE_STATUS         => 'TransactionInquiry',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 'TL',
        PosInterface::CURRENCY_USD => 'US',
        PosInterface::CURRENCY_EUR => 'EU',
        PosInterface::CURRENCY_GBP => 'GB',
        PosInterface::CURRENCY_JPY => 'JP',
        PosInterface::CURRENCY_RUB => 'RU',
    ];

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'ApiType'               => 'JSON',
            'ApiVersion'            => self::API_VERSION,
            'MerchantNo'            => $posAccount->getClientId(),
            'TerminalNo'            => $posAccount->getTerminalId(),
            'PaymentInstrumentType' => 'CARD',
            'IsEncrypted'           => 'N',
            'IsTDSecureMerchant'    => 'Y',
            'IsMailOrder'           => 'N',
            'ThreeDSecureData'      => [
                'SecureTransactionId' => $responseData['SecureTransactionId'],
                'CavvData'            => $responseData['CAVV'],
                'Eci'                 => $responseData['ECI'],
                'MdStatus'            => (int) $responseData['MdStatus'],
                'MD'                  => $responseData['MD'],
            ],
            'MACParams'             => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
            'Amount'                => $this->formatAmount($order['amount']),
            'CurrencyCode'          => $this->mapCurrency($order['currency']),
            'PointAmount'           => 0,
            'OrderId'               => self::formatOrderId($order['id']),
            'InstallmentCount'      => $this->mapInstallment($order['installment']),
            'InstallmentType'       => 'N',
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        $requestData['MAC'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'AdditionalInfoData'     => null,
            'CardInformationData'    => [
                'CardNo'         => $creditCard->getNumber(),
                'ExpireDate'     => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvc2'           => $creditCard->getCvv(),
                'CardHolderName' => $creditCard->getHolderName(),
            ],
            'IsMailOrder'            => 'N',
            'IsRecurring'            => null,
            'IsTDSecureMerchant'     => null,
            'PaymentInstrumentType'  => 'CARD',
            'ThreeDSecureData'       => null,
            'Amount'                 => $this->formatAmount($order['amount']),
            'CurrencyCode'           => $this->mapCurrency($order['currency']),
            'OrderId'                => self::formatOrderId($order['id']),
            'InstallmentCount'       => $this->mapInstallment($order['installment']),
            'InstallmentType'        => 'N',
            'KOICode'                => null,
            'MerchantMessageData'    => null,
            'PointAmount'            => null,
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo',
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'Amount'                 => $this->formatAmount($order['amount']),
            'CurrencyCode'           => $this->mapCurrency($order['currency']),
            'ReferenceCode'          => $order['ref_ret_num'],
            'InstallmentCount'       => $this->mapInstallment($order['installment']),
            'InstallmentType'        => 'N',
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => 'N',
            'PaymentFacilitatorData' => null,
            'OrderId'                => self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']),
        ];
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->mapTxType($order['transaction_type']),
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData['ReferenceCode'] = $order['ref_ret_num'];
        } else {
            $requestData['OrderId'] = self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']);
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->mapTxType($order['transaction_type']),
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData['ReferenceCode'] = $order['ref_ret_num'];
        } else {
            $requestData['OrderId'] = self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']);
        }

        if ($order['payment_model'] === PosInterface::MODEL_NON_SECURE) {
            $requestData['Amount']       = $this->formatAmount($order['amount']);
            $requestData['CurrencyCode'] = $this->mapCurrency($order['currency']);
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'ApiType'    => 'JSON',
            'ApiVersion' => self::API_VERSION,
            'MerchantNo' => $posAccount->getClientId(),
            'TerminalNo' => $posAccount->getTerminalId(),
        ];

        if (!isset($requestData['MAC'])) {
            if (null === $posAccount->getStoreKey()) {
                throw new \LogicException('Account storeKey eksik!');
            }

            $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');
        }

        return $requestData;
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
     * @param PosNetAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null, $extraData = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'MerchantNo'        => $posAccount->getClientId(),
            'TerminalNo'        => $posAccount->getTerminalId(),
            'PosnetID'          => $posAccount->getPosNetId(),
            'TransactionType'   => $this->mapTxType($txType),
            'OrderId'           => self::formatOrderId($order['id']),
            'Amount'            => (string) $this->formatAmount($order['amount']),
            'CurrencyCode'      => (string) $this->mapCurrency($order['currency']),
            'MerchantReturnURL' => (string) $order['success_url'],
            'InstallmentCount'  => $this->mapInstallment($order['installment']),
            'Language'          => $this->getLang($posAccount, $order),
            'TxnState'          => 'INITIAL',
            'OpenNewWindow'     => '0',
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $cardData = [
                'CardNo'         => $creditCard->getNumber(),
                // Kod calisiyor ancak burda bir tutarsizlik var: ExpireDate vs ExpiredDate
                // MacParams icinde ExpireDate olarak geciyor, gonderidigimizde ise ExpiredDate olarak istiyor.
                'ExpiredDate'    => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'Cvv'            => $creditCard->getCvv(),
                'CardHolderName' => (string) $creditCard->getHolderName(),

                'MacParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'UseOOS'    => '0',
            ];
        } else {
            $cardData = [
                /**
                 * UseOOS alanını 1 yaparak bankanın ortak ödeme sayfasının açılmasını ve
                 * bu ortak ödeme sayfası ile müşterinin kart bilgilerini girmesini sağlatabilir.
                 */
                'UseOOS'    => '1',
                'MacParams' => 'MerchantNo:TerminalNo:Amount',
            ];
        }

        $inputs += $cardData;

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            PosNetV1Pos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Mac'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * Get PrefixedOrderId
     * To check the status of an order or cancel/refund order PosNet
     * - requires the order length to be 24
     * - and order id prefix which is "TDS_" for 3D payments
     *
     * @param string $orderId
     * @param string $accountModel
     *
     * @return string
     */
    public static function mapOrderIdToPrefixedOrderId(string $orderId, string $accountModel): string
    {
        $prefix = self::ORDER_ID_REGULAR_PREFIX;
        if (PosInterface::MODEL_3D_SECURE === $accountModel) {
            $prefix = self::ORDER_ID_3D_PREFIX;
        } elseif (PosInterface::MODEL_3D_PAY === $accountModel) {
            $prefix = self::ORDER_ID_3D_PAY_PREFIX;
        }

        return $prefix.self::formatOrderId($orderId, self::ORDER_ID_TOTAL_LENGTH - strlen($prefix));
    }


    /**
     * formats order id by adding 0 pad to the left
     *
     * @param string   $orderId
     * @param int|null $padLength
     *
     * @return string
     */
    public static function formatOrderId(string $orderId, int $padLength = null): string
    {
        if (null === $padLength) {
            $padLength = self::ORDER_ID_LENGTH;
        }

        if (\strlen($orderId) > $padLength) {
            throw new InvalidArgumentException(\sprintf(
                // Banka tarafindan belirlenen kisitlama
                "Saglanan siparis ID'nin (%s) uzunlugu %d karakter. Siparis ID %d karakterden uzun olamaz!",
                $orderId,
                \strlen($orderId),
                $padLength
            ));
        }

        return \str_pad($orderId, $padLength, '0', STR_PAD_LEFT);
    }

    /**
     * Get amount
     * formats 10.01 to 1001
     *
     * @param float $amount
     *
     * @return int
     */
    protected function formatAmount(float $amount): int
    {
        return (int) (\round($amount, 2) * 100);
    }

    /**
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        if ($installment > 1) {
            return (string) $installment;
        }

        return '0';
    }


    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'id'          => $order['id'],
            'installment' => $order['installment'] ?? 0,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'          => $order['id'],
            'amount'      => $order['amount'],
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ref_ret_num' => $order['ref_ret_num'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return [
            'id'            => $order['id'],
            'payment_model' => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH,
            'amount'           => $order['amount'],
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];
    }
}
