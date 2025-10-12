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
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for GarantiPos Gateway requests
 */
class GarantiPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = '512';

    /**
     * MotoInd; işlemin MAilOrder bir işlem olup olmadığı bilgisinin gönderildiği alandır.
     * Y (also E) ise işlem mail order bir işlemdir.
     * N (also H) ise işlem ecommerce işlemidir.
     * @var string
     */
    private const MOTO = 'N';

    /** @var GarantiPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return GarantiPos::class === $gatewayClass;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $responseData['customeripaddress'],
            ],
            'Order'       => [
                'OrderID' => $responseData['orderid'],
            ],
            'Transaction' => [
                'Type'                  => $responseData['txntype'],
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
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

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Card'        => $this->getCardData($creditCard),
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType($txType),
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'                => $this->valueFormatter->formatAmount($order['amount']),
                'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        if (isset($order['recurring'])) {
            $result['Recurring'] = $this->createRecurringData($order['recurring']);
        }

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'              => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'Amount'            => $this->valueFormatter->formatAmount($order['amount']),
                'CurrencyCode'      => $this->valueMapper->mapCurrency($order['currency']),
                'OriginalRetrefNum' => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS),
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'                => $this->valueFormatter->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount, true),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'                => $this->valueFormatter->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount, true),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType($refundTxType),
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'                => $this->valueFormatter->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek,
                'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
                'OriginalRetrefNum'     => $order['ref_ret_num'],
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID' => $order['id'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_ORDER_HISTORY),
                'InstallmentCnt'        => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'                => $this->valueFormatter->formatAmount($order['amount']), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param GarantiPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        $result = [
            'Mode'        => $this->getMode(),
            'Version'     => self::API_VERSION,
            'Terminal'    => $this->getTerminalData($posAccount),
            'Customer'    => [
                'IPAddress' => $order['ip'],
            ],
            'Order'       => [
                'OrderID'     => null,
                'GroupID'     => null,
                'Description' => null,
                // Başlangıç ve bitiş tarihleri arasında en fazla 30 gün olabilir
                'StartDate'   => $this->valueFormatter->formatDateTime($order['start_date'], 'StartDate'),
                'EndDate'     => $this->valueFormatter->formatDateTime($order['end_date'], 'EndDate'),
                /**
                 * 500 adetten fazla işlem olması durumunda ListPageNum alanında diğer 500 lü grupların görüntülenmesi
                 * için sayfa numarası yazılır.
                 */
                'ListPageNum' => $order['page'],
            ],
            'Transaction' => [
                'Type'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_HISTORY),
                'Amount'                => $this->valueFormatter->formatAmount(1), //sabit olarak amount 100 gonderilecek
                'CurrencyCode'          => $this->valueMapper->mapCurrency(PosInterface::CURRENCY_TRY),
                'CardholderPresentCode' => '0',
                'MotoInd'               => self::MOTO,
            ],
        ];

        $result['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }


    /**
     * @param GarantiPosAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'secure3dsecuritylevel' => $this->valueMapper->mapSecureType($paymentModel),
            'mode'                  => $this->getMode(),
            'apiversion'            => self::API_VERSION,
            'terminalprovuserid'    => $posAccount->getUsername(),
            'terminaluserid'        => $posAccount->getUsername(),
            'terminalmerchantid'    => $posAccount->getClientId(),
            'terminalid'            => $posAccount->getTerminalId(),
            'txntype'               => $this->valueMapper->mapTxType($txType),
            'txnamount'             => (string) $this->valueFormatter->formatAmount($order['amount']),
            'txncurrencycode'       => (string) $this->valueMapper->mapCurrency($order['currency']),
            'txninstallmentcount'   => (string) $this->valueFormatter->formatInstallment($order['installment']),
            'orderid'               => (string) $order['id'],
            'successurl'            => (string) $order['success_url'],
            'errorurl'              => (string) $order['fail_url'],
            'customeripaddress'     => (string) $order['ip'],
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['cardnumber']          = $creditCard->getNumber();
            $inputs['cardexpiredatemonth'] = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'cardexpiredatemonth');
            $inputs['cardexpiredateyear']  = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'cardexpiredateyear');
            $inputs['cardcvv2']            = $creditCard->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            GarantiPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['secure3dhash'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @param GarantiPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'Mode'     => $this->getMode(),
            'Version'  => self::API_VERSION,
            'Terminal' => $this->getTerminalData($posAccount),
        ];

        if (!isset($requestData['Terminal']['HashData']) || '' === $requestData['Terminal']['HashData']) {
            $requestData['Terminal']['HashData'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
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
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return $this->prepareStatusOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return [
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'page'       => $data['page'] ?? 1,
            'ip'         => $data['ip'],
        ];
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
     * @param GarantiPosAccount $posAccount
     * @param bool              $isRefund
     *
     * @return array{ProvUserID: string, UserID: string, HashData: string, ID: string, MerchantID: string}
     */
    private function getTerminalData(AbstractPosAccount $posAccount, bool $isRefund = false): array
    {
        if (!$isRefund) {
            return [
                'ProvUserID' => $posAccount->getUsername(),
                'UserID'     => $posAccount->getUsername(),
                'HashData'   => '',
                'ID'         => $posAccount->getTerminalId(),
                'MerchantID' => $posAccount->getClientId(),
            ];
        }

        if (null === $posAccount->getRefundUsername()) {
            throw new \LogicException('Bu işlem için refundUsername tanımlı olması gerekir!');
        }

        return [
            'ProvUserID' => $posAccount->getRefundUsername(),
            'UserID'     => $posAccount->getRefundUsername(),
            'HashData'   => '',
            'ID'         => $posAccount->getTerminalId(),
            'MerchantID' => $posAccount->getClientId(),
        ];
    }

    /**
     * @param CreditCardInterface $creditCard
     *
     * @return array{Number: string, ExpireDate: string, CVV2: string}
     */
    private function getCardData(CreditCardInterface $creditCard): array
    {
        return [
            'Number'     => $creditCard->getNumber(),
            'ExpireDate' => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'ExpireDate'),
            'CVV2'       => $creditCard->getCvv(),
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
            'FrequencyType'     => $this->valueMapper->mapRecurringFrequency($recurringData['frequencyType']), //Monthly, weekly, daily
            'FrequencyInterval' => (string) $recurringData['frequency'],
            'Type'              => 'R', // R:Sabit Tutarli   G:Degisken Tuta
            //todo use formatter
            'StartDate'         => isset($recurringData['startDate']) ? $recurringData['startDate']->format('Ymd') : '',
        ];
    }
}
