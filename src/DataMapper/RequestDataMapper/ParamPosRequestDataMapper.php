<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for EstPos Gateway requests
 */
class ParamPosRequestDataMapper extends AbstractRequestDataMapper
{
    //todo
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'm/y';

    //todo
    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    //todo
    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'Y';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => 'TP_WMD_UCD',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => 'TP_Islem_Odeme_OnProv_WMD',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => 'TP_Islem_Odeme_OnProv_Kapa',
        PosInterface::TX_TYPE_CANCEL         => 'TP_Islem_Iptal_Iade_Kismi2', // todo on provizyon iptal: TP_Islem_Iptal_OnProv
        PosInterface::TX_TYPE_REFUND         => 'TP_Islem_Iptal_Iade_Kismi2',
        PosInterface::TX_TYPE_REFUND_PARTIAL => 'TP_Islem_Iptal_Iade_Kismi2',
        PosInterface::TX_TYPE_STATUS         => 'TP_Islem_Sorgulama4',
        PosInterface::TX_TYPE_HISTORY        => 'TP_Mutabakat_Ozet', // todo TP_Islem_Izleme?
    ];

    //todo
    /**
     * {@inheritdoc}
     */
    protected array $recurringOrderFrequencyMapping = [
        'DAY'   => 'D',
        'WEEK'  => 'W',
        'MONTH' => 'M',
        'YEAR'  => 'Y',
    ];

    //todo
    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE      => '3D',
        PosInterface::MODEL_3D_PAY         => '3d_pay',
        PosInterface::MODEL_3D_PAY_HOSTING => '3d_pay_hosting',
        PosInterface::MODEL_3D_HOST        => '3d_host',
        PosInterface::MODEL_NON_SECURE     => 'NS',
    ];

    /**
     * {@inheritdoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '1000',
        PosInterface::CURRENCY_USD => '1001',
        PosInterface::CURRENCY_EUR => '1002',
        PosInterface::CURRENCY_GBP => '1003',
    ];

    //todo

    /**
     * {@inheritDoc}
     *
     * @param array{UCD_MD: string, Islem_GUID: string, Siparis_ID: string, cavv: string, G: array{CLIENT_CODE: string, CLIENT_USERNAME: string, CLIENT_PASSWORD: string}} $responseData
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $requestData = $this->getRequestAccountData($posAccount) + [
                'UCD_MD'     => (string) $responseData['md'],
                'Islem_GUID' => (string) $responseData['islemGUID'],
                'Siparis_ID' => (string) $responseData['orderId'],
            ];
// //todo
//        if (isset($order['recurring'])) {
//            $requestData += $this->createRecurringData($order['recurring']);
//        }

        return $requestData;
    }

    /**
     * @param ParamPosAccount                      $posAccount
     * @param array<string, int|string|float|null> $order
     * @param CreditCardInterface                  $creditCard
     *
     * @return array
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'Islem_Guvenlik_Tip' => $this->secureTypeMappings[PosInterface::MODEL_3D_SECURE], //todo
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->formatAmount($order['amount']),
                'Toplam_Tutar'       => $this->formatAmount($order['amount']), //todo
                'Basarili_URL'       => (string) $order['success_url'],
                'Hata_URL'           => (string) $order['fail_url'],
                'Taksit'             => $this->mapInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_MONTH_FORMAT),
                'KK_SK_Yil'          => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_YEAR_FORMAT),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY === $order['currency']) {
            $requestData['Taksit'] = $this->mapInstallment((int) $order['installment']);
        } else {
            $requestData['Doviz_Kodu'] = $this->mapCurrency($order['currency']);
        }

        $requestData['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);
// todo
//        if (isset($order['recurring'])) {
//            $requestData += $this->createRecurringData($order['recurring']);
//        }

        return $requestData;
    }


    //todo

    /**
     * {@inheritDoc}
     * @return array<string, string|array<string, string>>
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'Islem_Guvenlik_Tip' => $this->secureTypeMappings[PosInterface::MODEL_3D_SECURE], //todo
                'Islem_ID'           => $this->crypt->generateRandomString(),
                'IPAdr'              => (string) $order['ip'],
                'Siparis_ID'         => (string) $order['id'],
                'Islem_Tutar'        => $this->formatAmount($order['amount']),
                'Toplam_Tutar'       => $this->formatAmount($order['amount']), //todo
                'Basarili_URL'       => (string) $order['success_url'],
                'Hata_URL'           => (string) $order['fail_url'],
                'Taksit'             => $this->mapInstallment((int) $order['installment']),
                'KK_Sahibi'          => $creditCard->getHolderName(),
                'KK_No'              => $creditCard->getNumber(),
                'KK_SK_Ay'           => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_MONTH_FORMAT),
                'KK_SK_Yil'          => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_YEAR_FORMAT),
                'KK_CVC'             => $creditCard->getCvv(),
                'KK_Sahibi_GSM'      => '', //optional olmasina ragmen hic gonderilmeyince hata aliniyor.
            ];

        if (PosInterface::CURRENCY_TRY === $order['currency']) {
            $requestData['Taksit'] = $this->mapInstallment((int) $order['installment']);
        } else {
            $requestData['Doviz_Kodu'] = $this->mapCurrency($order['currency']);
        }

        $requestData['Islem_Hash'] = $this->crypt->createHash($posAccount, $requestData);
// todo
//        if (isset($order['recurring'])) {
//            $requestData += $this->createRecurringData($order['recurring']);
//        }

        return $requestData;
    }

    //todo

    /**
     * {@inheritDoc}
     *
     * @return array{Type: string, OrderId: string, Name: string, Password: string, ClientId: string, Total: float|null}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'Type'    => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'OrderId' => (string) $order['id'],
                'Total'   => isset($order['amount']) ? (float) $this->formatAmount($order['amount']) : null,
            ];

        if (isset($order['amount'], $order['pre_auth_amount']) && $order['pre_auth_amount'] < $order['amount']) {
            // when amount < pre_auth_amount then we need to send PREAMT value
            $requestData['Extra']['PREAMT'] = $order['pre_auth_amount'];
        }

        return $requestData;
    }

    //todo

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $statusRequestData = $this->getRequestAccountData($posAccount) + [
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

    //todo

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
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

            return $this->getRequestAccountData($posAccount) + $orderData;
        }

        return $this->getRequestAccountData($posAccount) + [
                'OrderId' => $order['id'],
                'Type'    => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
            ];
    }

    //todo

    /**
     * {@inheritDoc}
     * @return array{OrderId: string, Currency: string, Type: string, Total?: string, Name: string, Password: string, ClientId: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = [
            'OrderId'  => (string) $order['id'],
            'Currency' => $this->mapCurrency($order['currency']),
            'Type'     => $this->mapTxType($refundTxType),
        ];

        if (isset($order['amount'])) {
            $requestData['Total'] = (string) $order['amount'];
        }

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    //todo

    /**
     * {@inheritDoc}
     * @return array{OrderId: string, Extra: array<string, string>&array, Name: string, Password: string, ClientId: string}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $requestData = [
            'OrderId' => (string) $order['id'],
            'Extra'   => [
                $this->mapTxType(PosInterface::TX_TYPE_HISTORY) => 'QUERY',
            ],
        ];

        return $this->getRequestAccountData($posAccount) + $requestData;
    }

    //todo

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
    public function create3DFormData(?AbstractPosAccount $posAccount, ?array $order, string $paymentModel, string $txType, ?string $gatewayURL = null, ?CreditCardInterface $creditCard = null, array $extraData = [])
    {
        if (isset($extraData['UCD_URL'])) {
            return [
                'gateway' => $extraData['UCD_URL'],
                'method'  => 'POST',
                'inputs'  => [],
            ];
        }

        return $extraData['UCD_HTML'];
    }

    //todo

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    //todo

    /**
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param AbstractPosAccount                                                        $posAccount
     * @param array<string, string|int|float|null>                                      $order
     * @param string                                                                    $paymentModel
     * @param string                                                                    $txType
     * @param string                                                                    $gatewayURL
     * @param CreditCardInterface|null                                                  $creditCard
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * @throws UnsupportedTransactionTypeException
     */
    protected function create3DFormDataCommon(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $inputs = [
            'clientid'  => $posAccount->getClientId(),
            'storetype' => $this->secureTypeMappings[$paymentModel],
            'amount'    => (string) $order['amount'],
            'oid'       => (string) $order['id'],
            'okUrl'     => (string) $order['success_url'],
            'failUrl'   => (string) $order['fail_url'],
            'rnd'       => $this->crypt->generateRandomString(),
            'lang'      => $this->getLang($posAccount, $order),
            'currency'  => $this->mapCurrency((string) $order['currency']),
            'taksit'    => $this->mapInstallment((int) $order['installment']),
            'islemtipi' => $this->mapTxType($txType),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['pan']                             = $creditCard->getNumber();
            $inputs['Ecom_Payment_Card_ExpDate_Month'] = $creditCard->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $inputs['Ecom_Payment_Card_ExpDate_Year']  = $creditCard->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $inputs['cv2']                             = $creditCard->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * 0 => '1'
     * 1 => '1'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '1';
    }

    //todo

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY, //todo doviz odeme nasil olacak?
            'amount'      => $order['amount'],
            'success_url' => $order['success_url'],
            'fail_url'    => $order['fail_url'],
            'ip'          => $order['ip'],
        ]);
    }

    //todo

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'              => $order['id'],
            'amount'          => $order['amount'] ?? null,
            'pre_auth_amount' => $order['pre_auth_amount'] ?? null,
        ];
    }


//todo

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

    //todo

    /**
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return [
            'id' => $order['id'],
        ];
    }

    //todo

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
     * 10.0 => 10,00
     * 1000.5 => 1000,50
     * @param float $amount
     *
     * @return string
     */
    protected function formatAmount(float $amount): string
    {
        return \number_format($amount, 2, ',', '');
    }

    /**
     * @param AbstractPosAccount $posAccount
     *
     * @return array{G: array{CLIENT_CODE: string, CLIENT_USERNAME: string, CLIENT_PASSWORD: string}}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'G'    => [
                'CLIENT_CODE'     => $posAccount->getClientId(),
                'CLIENT_USERNAME' => $posAccount->getUsername(),
                'CLIENT_PASSWORD' => $posAccount->getPassword(),
            ],
            'GUID' => $posAccount->getStoreKey(),
        ];
    }

    //todo

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
