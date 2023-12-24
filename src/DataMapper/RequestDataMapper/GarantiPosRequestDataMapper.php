<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\GarantiPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\PosInterface;

/**
 * Creates request data for GarantiPos Gateway requests
 */
class GarantiPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = '512';

    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';

    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /** @var string */
    private const MOTO = 'N';

    /**
     * {@inheritDoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE => '3D',
        PosInterface::MODEL_3D_PAY    => '3D_PAY',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH      => 'sales',
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => 'preauth',
        PosInterface::TX_TYPE_PAY_POST_AUTH => 'postauth',
        PosInterface::TX_TYPE_CANCEL        => 'void',
        PosInterface::TX_TYPE_REFUND        => 'refund',
        PosInterface::TX_TYPE_HISTORY       => 'orderhistoryinq',
        PosInterface::TX_TYPE_STATUS        => 'orderinq',
    ];

    protected array $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
    ];

    /** @var GarantiPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account),
            'Customer'    => [
                'IPAddress'    => $responseData['customeripaddress'],
            ],
            'Order'       => [
                'OrderID'     => $responseData['orderid'],
            ],
            'Transaction' => [
                'Type'                  => $responseData['txntype'],
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $responseData['txnamount'],
                'CurrencyCode'          => $responseData['txncurrencycode'],
                'CardholderPresentCode' => '13', //13 for 3D secure payment
                'MotoInd'               => self::MOTO,
                'Secure3D'              => [
                    'AuthenticationCode' => $responseData['cavv'],
                    'SecurityLevel'      => $responseData['eci'],
                    'TxnID'              => $responseData['xid'],
                    'Md'                 => $responseData['md'],
                ],
            ],
        ];

        if (isset($order['recurring'])) {
            $result['Recurring'] = $this->createRecurringData($order['recurring']);
        }

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Card'        => $this->getCardData($card),
            'Order'       => [
                'OrderID'     => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->mapTxType($txType),
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $this->formatAmount($order['amount']),
                'CurrencyCode'          => $this->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        if (isset($order['recurring'])) {
            $result['Recurring'] = $this->createRecurringData($order['recurring']);
        }

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'              => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'Amount'            => $this->formatAmount($order['amount']),
                'CurrencyCode'      => $this->mapCurrency($order['currency']),
                'OriginalRetrefNum' => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->mapTxType(PosInterface::TX_TYPE_STATUS),
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $this->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, true),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $this->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account, true),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->mapTxType(PosInterface::TX_TYPE_REFUND),
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $this->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek,
                'CurrencyCode'          => $this->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $account
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        $order = $this->prepareHistoryOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($account),
            'Customer'    => [
                'IPAddress'    => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->mapTxType(PosInterface::TX_TYPE_HISTORY),
                'InstallmentCnt'        => $this->mapInstallment($order['installment']),
                'Amount'                => $this->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($account, $result);

        return $result;
    }


    /**
     * @param GarantiPosAccount $account
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'secure3dsecuritylevel' => $this->secureTypeMappings[$paymentModel],
            'mode'                  => $this->getMode(),
            'apiversion'            => self::API_VERSION,
            'terminalprovuserid'    => $account->getUsername(),
            'terminaluserid'        => $account->getUsername(),
            'terminalmerchantid'    => $account->getClientId(),
            'terminalid'            => $account->getTerminalId(),
            'txntype'               => $this->mapTxType($txType),
            'txnamount'             => (string) $this->formatAmount($order['amount']),
            'txncurrencycode'       => $this->mapCurrency($order['currency']),
            'txninstallmentcount'   => $this->mapInstallment($order['installment']),
            'orderid'               => (string) $order['id'],
            'successurl'            => (string) $order['success_url'],
            'errorurl'              => (string) $order['fail_url'],
            'customeripaddress'     => (string) $order['ip'],
        ];

        if ($card instanceof CreditCardInterface) {
            $inputs['cardnumber']          = $card->getNumber();
            $inputs['cardexpiredatemonth'] = $card->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['cardexpiredateyear']  = $card->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['cardcvv2']            = $card->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent($inputs, $account->getBank(), $txType, $paymentModel);
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getRequestData();

        $inputs['secure3dhash'] = $this->crypt->create3DHash($account, $inputs);

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
     * Amount Formatter
     * converts 100 to 10000, or 10.01 to 1001
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
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'      => $order['amount'],
            'ip'          => $order['ip'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'          => $order['id'],
            'ref_ret_num' => $order['ref_ret_num'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'      => $order['amount'],
            'ip'          => $order['ip'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return [
            'id'          => $order['id'],
            'amount'      => 1, //sabit deger gonderilmesi gerekiyor
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ip'          => $order['ip'] ?? '',
            'installment' => 0,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order): array
    {
        return $this->prepareStatusOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return [
            'id'          => $order['id'],
            'amount'      => 1, //sabit deger gonderilmesi gerekiyor
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ref_ret_num' => $order['ref_ret_num'],
            'ip'          => $order['ip'] ?? '',
            'installment' => 0,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        $refundOrder = $this->prepareCancelOrder($order);
        // just checking if amount is exist
        $refundOrder['amount'] = $order['amount'];

        return $refundOrder;
    }

    /**
     * @return string
     */
    private function getMode(): string
    {
        return $this->isTestMode() ? 'TEST' : 'PROD';
    }

    /**
     * @param GarantiPosAccount $account
     * @param bool              $isRefund
     *
     * @return array{ProvUserID: string, UserID: string, HashData: string, ID: string, MerchantID: string}
     */
    private function getTerminalData(AbstractPosAccount $account, bool $isRefund = false): array
    {
        if (!$isRefund) {
            return [
                'ProvUserID' => $account->getUsername(),
                'UserID'     => $account->getUsername(),
                'HashData'   => '',
                'ID'         => $account->getTerminalId(),
                'MerchantID' => $account->getClientId(),
            ];
        }

        if (null === $account->getRefundUsername()) {
            throw new \LogicException('Bu işlem için refundUsername tanımlı olması gerekir!');
        }

        return [
            'ProvUserID' => $account->getRefundUsername(),
            'UserID'     => $account->getRefundUsername(),
            'HashData'   => '',
            'ID'         => $account->getTerminalId(),
            'MerchantID' => $account->getClientId(),
        ];
    }

    /**
     * @param CreditCardInterface $card
     *
     * @return array{Number: string, ExpireDate: string, CVV2: string}
     */
    private function getCardData(CreditCardInterface $card): array
    {
        return [
            'Number'     => $card->getNumber(),
            'ExpireDate' => $card->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
            'CVV2'       => $card->getCvv(),
        ];
    }

    /**
     * ornek:
     * <Recurring>
     *   <Type>G veya R</Type> R:Sabit Tutarli   G:Degisken Tutar
     *   <TotalPaymentNum></TotalPaymentNum>
     *   <FrequencyType>M , W , D </FrequencyType> Monthly, weekly, daily
     *   <FrequencyInterval></FrequencyInterval>
     *   <StartDate></StartDate>
     *   <PaymentList>
     *       <Payment>
     *           <PaymentNum></PaymentNum>
     *           <Amount></Amount>
     *           <DueDate></DueDate> YYYYMMDD
     *       </Payment>
     *   </PaymentList>
     * </Recurring>
     *
     * @param array{installment: int, frequencyType: string, frequency: int, startDate?: \DateTimeInterface} $recurringData
     *
     * @return array{TotalPaymentNum: string, FrequencyType: string, FrequencyInterval: string, Type: mixed, StartDate: string}
     */
    private function createRecurringData(array $recurringData): array
    {
        return [
            'TotalPaymentNum'   => (string) $recurringData['installment'], //kac kere tekrarlanacak
            'FrequencyType'     => $this->mapRecurringFrequency($recurringData['frequencyType']), //Monthly, weekly, daily
            'FrequencyInterval' => (string) $recurringData['frequency'],
            'Type'              => 'R', // R:Sabit Tutarli   G:Degisken Tuta
            'StartDate'         => isset($recurringData['startDate']) ? $recurringData['startDate']->format('Ymd') : '',
        ];
    }
}
