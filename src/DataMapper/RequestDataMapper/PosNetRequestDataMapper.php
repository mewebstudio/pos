<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use InvalidArgumentException;
use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\PosNetCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PosNet Gateway requests
 */
class PosNetRequestDataMapper extends AbstractRequestDataMapper
{
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
    private const ORDER_ID_3D_PREFIX = 'TDSC';

    /** @var string */
    private const ORDER_ID_3D_PAY_PREFIX = ''; //?

    /** @var string */
    private const ORDER_ID_REGULAR_PREFIX = '';  //?

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'Sale',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'Auth',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'Capt',
        PosInterface::TX_TYPE_CANCEL         => 'reverse',
        PosInterface::TX_TYPE_REFUND         => 'return',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'return',
        PosInterface::TX_TYPE_STATUS         => 'agreement',
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

    /** @var PosNetCrypt  */
    protected CryptInterface $crypt;

    /**
     * @param PosNetAccount                                                     $posAccount
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType kullanilmiyor
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $mappedOrder             = [];
        $mappedOrder['id']       = self::formatOrderId($order['id']);
        $mappedOrder['amount']   = $this->formatAmount($order['amount']);
        $mappedOrder['currency'] = (string) $this->mapCurrency($order['currency']);

        $requestData = [
            'mid'         => $posAccount->getClientId(),
            'tid'         => $posAccount->getTerminalId(),
            'oosTranData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
                'wpAmount'     => 0,
            ],
        ];

        $requestData['oosTranData']['mac'] = $this->crypt->createHash($posAccount, $requestData, $mappedOrder);

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

        return [
            'mid'                                 => $posAccount->getClientId(),
            'tid'                                 => $posAccount->getTerminalId(),
            'tranDateRequired'                    => '1',
            strtolower($this->mapTxType($txType)) => [
                'orderID'      => self::formatOrderId($order['id']),
                'installment'  => $this->mapInstallment($order['installment']),
                'amount'       => $this->formatAmount($order['amount']),
                'currencyCode' => $this->mapCurrency($order['currency']),
                'ccno'         => $creditCard->getNumber(),
                'expDate'      => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'cvc'          => $creditCard->getCvv(),
            ],
        ];
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return [
            'mid'                                                   => $posAccount->getClientId(),
            'tid'                                                   => $posAccount->getTerminalId(),
            'tranDateRequired'                                      => '1',
            \strtolower($this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH)) => [
                'hostLogKey'   => $order['ref_ret_num'],
                'amount'       => $this->formatAmount($order['amount']),
                'currencyCode' => $this->mapCurrency($order['currency']),
                'installment'  => $this->mapInstallment($order['installment']),
            ],
        ];
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $txType = $this->mapTxType(PosInterface::TX_TYPE_STATUS);

        return [
            'mid'   => $posAccount->getClientId(),
            'tid'   => $posAccount->getTerminalId(),
            $txType => [
                'orderID' => self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']),
            ],
        ];
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $txType      = $this->mapTxType(PosInterface::TX_TYPE_CANCEL);
        $requestData = [
            'mid'              => $posAccount->getClientId(),
            'tid'              => $posAccount->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => [
                'transaction' => 'sale',
            ],
        ];

        if (isset($order['auth_code'])) {
            $requestData[$txType]['authCode'] = $order['auth_code'];
        }

        //either will work
        if (isset($order['ref_ret_num'])) {
            $requestData[$txType]['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']);
        }

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

        $txType      = $this->mapTxType($refundTxType);
        $requestData = [
            'mid'              => $posAccount->getClientId(),
            'tid'              => $posAccount->getTerminalId(),
            'tranDateRequired' => '1',
            $txType            => [
                'amount'       => $this->formatAmount($order['amount']),
                'currencyCode' => $this->mapCurrency($order['currency']),
            ],
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData[$txType]['hostLogKey'] = $order['ref_ret_num'];
        } else {
            $requestData[$txType]['orderID'] = self::mapOrderIdToPrefixedOrderId($order['id'], $order['payment_model']);
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
     * @param array{data1: string, data2: string, sign: string} $extraData
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     *
     * @throws InvalidArgumentException
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null, array $extraData = null): array
    {
        if (null === $extraData) {
            throw new InvalidArgumentException('$extraData can not be null');
        }

        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'mid'               => $posAccount->getClientId(),
            'posnetID'          => $posAccount->getPosNetId(),
            'posnetData'        => $extraData['data1'], //Ödeme bilgilerini içermektedir.
            'posnetData2'       => $extraData['data2'], //Kart bilgileri request içerisinde bulunuyorsa bu alan oluşturulmaktadır
            'digest'            => $extraData['sign'],  //Servis imzası.
            'merchantReturnURL' => $order['success_url'],
            /**
             * url - Yönlendirilen sayfanın adresi (URL – bilgi amaçlı)
             * YKB tarafından verilen Java Script fonksiyonu (posnet.js içerisindeki) tarafından
             * set edilir. Form içerisinde bulundurulması yeterlidir.
             */
            'url'               => '',
            'lang'              => $this->getLang($posAccount, $order),
        ];

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param PosNetAccount                        $posAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        if (null === $creditCard->getHolderName() && isset($order['name'])) {
            $creditCard->setHolderName($order['name']);
        }

        return [
            'mid'            => $posAccount->getClientId(),
            'tid'            => $posAccount->getTerminalId(),
            'oosRequestData' => [
                'posnetid'       => $posAccount->getPosNetId(),
                'ccno'           => $creditCard->getNumber(),
                'expDate'        => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                'cvc'            => $creditCard->getCvv(),
                'amount'         => $this->formatAmount($order['amount']),
                'currencyCode'   => $this->mapCurrency($order['currency']),
                'installment'    => $this->mapInstallment($order['installment']),
                'XID'            => self::formatOrderId($order['id']),
                'cardHolderName' => $creditCard->getHolderName(),
                'tranType'       => $this->mapTxType($txType),
            ],
        ];
    }

    /**
     * @param PosNetAccount                        $posAccount
     * @param array<string, int|string|float|null> $order
     * @param array<string, mixed>                 $responseData
     *
     * @return array<string, string|array<string, string>>
     */
    public function create3DResolveMerchantRequestData(AbstractPosAccount $posAccount, array $order, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $mappedOrder             = [];
        $mappedOrder['id']       = self::formatOrderId($order['id']);
        $mappedOrder['amount']   = $this->formatAmount($order['amount']);
        $mappedOrder['currency'] = (string) $this->mapCurrency($order['currency']);

        $requestData = [
            'mid'                    => $posAccount->getClientId(),
            'tid'                    => $posAccount->getTerminalId(),
            'oosResolveMerchantData' => [
                'bankData'     => $responseData['BankPacket'],
                'merchantData' => $responseData['MerchantPacket'],
                'sign'         => $responseData['Sign'],
            ],
        ];

        $requestData['oosResolveMerchantData']['mac'] = $this->crypt->createHash($posAccount, $requestData, $mappedOrder);

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + [
            'mid' => $posAccount->getClientId(),
            'tid' => $posAccount->getTerminalId(),
        ];
    }

    /**
     * Get PrefixedOrderId
     * To check the status of an order or cancel/refund order Yapikredi
     * - requires the order length to be 24
     * - and order id prefix which is "TDSC" for 3D payments
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
     * 0 => '00'
     * 1 => '00'
     * 2 => '02'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        if ($installment > 1) {
            return \str_pad((string) $installment, 2, '0', STR_PAD_LEFT);
        }

        return '00';
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
        $orderTemp = [
            //id or ref_ret_num
            'id'          => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            //optional
            'auth_code'   => $order['auth_code'] ?? null,
        ];

        if (null !== $orderTemp['id']) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
        }

        return $orderTemp;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        $orderTemp = [
            //id or ref_ret_num
            'id'          => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];

        if (null !== $orderTemp['id']) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE;
        }

        return $orderTemp;
    }
}
