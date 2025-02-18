<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use DateTimeInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\AkbankPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for AkbankPos Gateway requests
 */
class AkbankPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'my';

    public const API_VERSION = '1.00';

    /**
     * Not: Güvenli Ödeme 3D Model isteklerinde opsiyonel
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_NON_SECURE => '1000',
            PosInterface::MODEL_3D_SECURE  => '3000',
            PosInterface::MODEL_3D_PAY     => '3000',
            PosInterface::MODEL_3D_HOST    => '3000',
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_NON_SECURE => '1004',
            PosInterface::MODEL_3D_SECURE  => '3004',
            PosInterface::MODEL_3D_PAY     => '3004',
            PosInterface::MODEL_3D_HOST    => '3004',
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '1005',
        PosInterface::TX_TYPE_REFUND         => '1002',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '1002',
        PosInterface::TX_TYPE_CANCEL         => '1003',
        PosInterface::TX_TYPE_ORDER_HISTORY  => '1010',
        PosInterface::TX_TYPE_HISTORY        => '1009',
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
        PosInterface::MODEL_3D_SECURE  => '3D',
        PosInterface::MODEL_3D_PAY     => '3D_PAY',
        PosInterface::MODEL_3D_HOST    => '3D_PAY_HOSTING',
        PosInterface::MODEL_NON_SECURE => 'PAY_HOSTING',
    ];

    /** @var array<PosInterface::LANG_*, string> */
    protected array $langMappings = [
        PosInterface::LANG_TR => 'TR',
        PosInterface::LANG_EN => 'EN',
    ];

    /**
     * @var non-empty-array<PosInterface::CURRENCY_*, int>
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => 949,
        PosInterface::CURRENCY_USD => 840,
        PosInterface::CURRENCY_EUR => 978,
        PosInterface::CURRENCY_JPY => 392,
        PosInterface::CURRENCY_RUB => 643,
    ];

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'version'           => self::API_VERSION,
                'txnCode'           => $this->mapTxType($txType, PosInterface::MODEL_NON_SECURE),
                'requestDateTime'   => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'      => $this->crypt->generateRandomString(),
                'order'             => [
                    'orderId' => (string) $order['id'],
                ],
                'transaction'       => [
                    'amount'       => $this->formatAmount($order['amount']),
                    'currencyCode' => $this->mapCurrency((string) $order['currency']),
                    'motoInd'      => 0,
                    'installCount' => $this->mapInstallment((int) $order['installment']),
                ],
                'secureTransaction' => [
                    'secureId'      => $responseData['secureId'],
                    'secureEcomInd' => $responseData['secureEcomInd'],
                    'secureData'    => $responseData['secureData'],
                    'secureMd'      => $responseData['secureMd'],
                ],
                'customer'          => [
                    'ipAddress' => $order['ip'],
                ],
            ];
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->mapTxType($txType, PosInterface::MODEL_NON_SECURE),
                'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'card'            => [
                    'cardNumber' => $creditCard->getNumber(),
                    'cvv2'       => $creditCard->getCvv(),
                    'expireDate' => $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT),
                ],
                'transaction'     => [
                    'amount'       => $this->formatAmount($order['amount']),
                    'currencyCode' => $this->mapCurrency((string) $order['currency']),
                    'motoInd'      => 0,
                    'installCount' => $this->mapInstallment((int) $order['installment']),
                ],
                'customer'        => [
                    'ipAddress' => $order['ip'],
                ],
            ];

        if (isset($order['recurring'])) {
            $requestData += $this->createRecurringData($order['recurring']);
            // todo motoInd olmak zorundami?
            $requestData['transaction']['motoInd'] = 1;
            $requestData['order']                  = [
                'orderTrackId' => (string) $order['id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     *
     * @return array<string, mixed>
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'order'           => [
                    'orderId' => (string) $order['id'],
                ],
                'transaction'     => [
                    'amount'       => $this->formatAmount($order['amount']),
                    'currencyCode' => $this->mapCurrency((string) $order['currency']),
                ],
                'customer'        => [
                    'ipAddress' => $order['ip'],
                ],
            ];
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'txnCode'         => $this->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'version'         => self::API_VERSION,
                'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'    => $this->crypt->generateRandomString(),
            ];

        if (\array_key_exists('recurringOrderInstallmentNumber', $order)) {
            /**
             * Henüz provizyon almamış ileri tarihli talimat işlemini veya recurring işlemi iptal etmek için kullanılmaktadır.
             */
            if (null !== $order['recurringOrderInstallmentNumber']) {
                /**
                 * Recurring işlem talimatlarının tamamı iptal edilmek isteniyorsa, recurringOrder parametresi gönderilmemelidir.
                 * Recurring işlem talimatlarından sadece biri iptal edilmek isteniyorsa,
                 * ilgili talimatın recurringOrder değeri işlem isteğinde iletilmelidir.
                 */
                $requestData['recurring'] = [
                    'recurringOrder' => $order['recurringOrderInstallmentNumber'],
                ];
                if (isset($order['recurring_payment_is_pending']) && true === $order['recurring_payment_is_pending']) {
                    $requestData['txnCode'] = '1013';
                }
            } else {
                $requestData['txnCode'] = '1013';
            }

            $requestData['order'] = [
                'orderTrackId' => (string) $order['recurring_id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * Eğer kısmi tutarlı iade işlemi yapılmak isteniyorsa, iade işlemi requestinde transaction alanı gönderilmelidir.
     * Eğer transaction alanı gönderilmezse, iade işlemi tam tutarlı olarak gerçekleşecektir.
     *
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->mapTxType($refundTxType),
                'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'transaction'     => [
                    'amount'       => $this->formatAmount($order['amount']),
                    'currencyCode' => $this->mapCurrency((string) $order['currency']),
                ],
            ];

        if (isset($order['recurringOrderInstallmentNumber'])) {
            /**
             * Provizyon almış Recurring ve/veya İleri tarihli Satış işlemlerinin iadesi yapılabilir.
             * Ön Provizyon işlemlerinin iadesi yapılamamaktadır.
             */
            $requestData['recurring'] = [
                'recurringOrder' => $order['recurringOrderInstallmentNumber'],
            ];

            $requestData['order'] = [
                'orderTrackId' => (string) $order['recurring_id'],
            ];
        } else {
            $requestData['order'] = [
                'orderId' => (string) $order['id'],
            ];
        }

        return $requestData;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'version'         => self::API_VERSION,
                'txnCode'         => $this->mapTxType(PosInterface::TX_TYPE_ORDER_HISTORY, PosInterface::MODEL_NON_SECURE),
                'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
                'randomNumber'    => $this->crypt->generateRandomString(),
                'order'           => [],
            ];

        if (isset($order['recurring_id'])) {
            $result['order']['orderTrackId'] = $order['recurring_id'];
        } else {
            $result['order']['orderId'] = $order['id'];
        }

        return $result;
    }

    /**
     * İşlem cevabında, sadece 9999 adet işlem sorgulanabilir.
     * Tarih aralığında, 9999 adet işlemden daha fazla işlem olması durumunda,
     * “VPS-2235” - "Toplam kayıt sayısı aşıldı. Batch No girerek ilerleyiniz.” hatası verilecektir.
     *
     * @param AkbankPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $order = $this->prepareHistoryOrder($data);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'randomNumber' => $this->crypt->generateRandomString(),
            ];
        if (isset($order['batch_num'])) {
            $requestData['report'] = [
                'batchNumber'   => $order['batch_num'],
            ];
        } elseif (isset($order['start_date'], $order['end_date'])) {
            $requestData['report'] = [
                'startDateTime' => $this->formatRequestDateTime($order['start_date']),
                'endDateTime'   => $this->formatRequestDateTime($order['end_date']),
            ];
        }

        return $requestData;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'paymentModel'    => $this->secureTypeMappings[$paymentModel],
            'txnCode'         => $this->mapTxType($txType, $paymentModel),
            'merchantSafeId'  => $posAccount->getClientId(),
            'terminalSafeId'  => $posAccount->getTerminalId(),
            'orderId'         => (string) $order['id'],
            'lang'            => $this->getLang($posAccount, $order),
            'amount'          => $this->formatAmount($order['amount']),
            'currencyCode'    => (string) $this->mapCurrency((string) $order['currency']),
            'installCount'    => (string) $this->mapInstallment((int) $order['installment']),
            'okUrl'           => (string) $order['success_url'],
            'failUrl'         => (string) $order['fail_url'],
            'randomNumber'    => $this->crypt->generateRandomString(),
            'requestDateTime' => $this->formatRequestDateTime($order['transaction_time']),
        ];

        if (null !== $posAccount->getSubMerchantId()) {
            $inputs['subMerchantId'] = $posAccount->getSubMerchantId();
        }

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['creditCard']  = $creditCard->getNumber();
            $inputs['expiredDate'] = $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['cvv']         = $creditCard->getCvv();
        }

        $data = [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];

        $event = new Before3DFormHashCalculatedEvent(
            $data['inputs'],
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            AkbankPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $data['inputs'] = $event->getFormInputs();

        $data['inputs']['hash'] = $this->crypt->create3DHash($posAccount, $data['inputs']);

        return $data;
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData
            + $this->getRequestAccountData($posAccount)
            + [
                'version'           => self::API_VERSION,
                'requestDateTime'   => $requestData['requestDateTime'] ?? $this->formatRequestDateTime($this->createDateTime()),
                'randomNumber'      => $this->crypt->generateRandomString(),
            ];
    }

    /**
     * 0 => 1
     * 1 => 1
     * 2 => 2
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): int
    {
        return \max($installment, 1);
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        if (isset($order['recurring'])) {
            $order['installment'] = 0;
        }

        return \array_merge($order, [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'ip'               => $order['ip'],
            'installment'      => $order['installment'] ?? 0,
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'transaction_time' => $this->createDateTime(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'               => $order['id'],
            'amount'           => $order['amount'],
            'currency'         => $order['currency'],
            'ip'               => $order['ip'],
            'transaction_time' => $this->createDateTime(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'transaction_time' => $this->createDateTime(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'amount'           => $order['amount'],
            'transaction_time' => $this->createDateTime(),
        ]);
    }

    /**
     * prepares order for cancel request
     *
     * @param array<string, mixed> $order
     *
     * @return non-empty-array<string, mixed>
     */
    protected function prepareCancelOrder(array $order): array
    {
        return \array_merge($order, [
            'id'               => $order['id'] ?? null,
            'recurring_id'     => $order['recurring_id'] ?? null,
            'transaction_time' => $this->createDateTime(),
        ]);
    }

    /**
     * @param float $amount
     *
     * @return string
     */
    protected function formatAmount(float $amount): string
    {
        return \number_format($amount, 2, '.', '');
    }

    /**
     * @inheritDoc
     *
     * @return int
     */
    protected function mapCurrency(string $currency): int
    {
        return $this->currencyMappings[$currency];
    }

    /**
     * prepares history request
     *
     * @param array<string, mixed> $data
     *
     * @return array{batch_num?: int, start_date?: DateTimeInterface, end_date?: DateTimeInterface}
     */
    protected function prepareHistoryOrder(array $data): array
    {
        if (isset($data['batch_num'])) {
            return [
                'batch_num' => $data['batch_num'],
            ];
        }

        return [
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
        ];
    }

    /**
     * @param AkbankPosAccount $posAccount
     *
     * @return array{terminal: array{merchantSafeId: string, terminalSafeId: string}}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        $data = [
            'terminal' => [
                'merchantSafeId' => $posAccount->getClientId(),
                'terminalSafeId' => $posAccount->getTerminalId(),
            ],
        ];

        if (null !== $posAccount->getSubMerchantId()) {
            $data['subMerchant'] = [
                'subMerchantId' => $posAccount->getSubMerchantId(),
            ];
        }

        return $data;
    }

    /**
     * @param array{frequency: int<1, 99>, frequencyType: string, installment: int<2, 120>} $recurringData
     *
     * @return array{recurring: array{frequencyInterval: int<1, 99>, frequencyCycle: string, numberOfPayments: int<2,
     *                          120>}}
     */
    private function createRecurringData(array $recurringData): array
    {
        return [
            'recurring' => [
                // Periyodik İşlem Frekansı
                'frequencyInterval' => $recurringData['frequency'],
                // D|W|M|Y
                'frequencyCycle'    => $this->mapRecurringFrequency($recurringData['frequencyType']),
                'numberOfPayments'  => $recurringData['installment'],
            ],
        ];
    }

    /**
     * @return \DateTimeImmutable
     */
    private function createDateTime(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Europe/Istanbul'));
    }

    /**
     * @param DateTimeInterface $dateTime
     *
     * @return string example 2024-04-14T16:45:30.000
     */
    private function formatRequestDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d\TH:i:s').'.000';
    }
}
