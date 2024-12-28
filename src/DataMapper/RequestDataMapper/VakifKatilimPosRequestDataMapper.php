<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Crypt\CryptInterface;
use Mews\Pos\Crypt\KuveytPosCrypt;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\PosInterface;

/**
 * Creates request data for Vakif Katilim Gateway requests
 */
class VakifKatilimPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = '1.0.0';

    /** @var string */
    public const CREDIT_CARD_EXP_YEAR_FORMAT = 'y';

    /** @var string */
    public const CREDIT_CARD_EXP_MONTH_FORMAT = 'm';

    /**
     * {@inheritdoc}
     */
    protected array $secureTypeMappings = [
        PosInterface::MODEL_3D_SECURE  => '3',
        PosInterface::MODEL_NON_SECURE => '5',
    ];

    /**
     * Currency mapping
     *
     * {@inheritdoc}
     */
    protected array $currencyMappings = [
        PosInterface::CURRENCY_TRY => '0949',
        PosInterface::CURRENCY_USD => '0840',
        PosInterface::CURRENCY_EUR => '0978',
        PosInterface::CURRENCY_GBP => '0826',
        PosInterface::CURRENCY_JPY => '0392',
        PosInterface::CURRENCY_RUB => '0810',
    ];

    /** @var KuveytPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     * @return array<string, mixed>
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'OkUrl'               => $order['success_url'],
                'FailUrl'             => $order['fail_url'],
                'HashData'            => '',
                'APIVersion'          => self::API_VERSION,
                'AdditionalData'      => [
                    'AdditionalDataList' => [
                        'VPosAdditionalData' => [
                            'Key'  => 'MD',
                            'Data' => $responseData['MD'],
                        ],
                    ],
                ],
                'InstallmentCount'    => $this->mapInstallment($order['installment']),
                'Amount'              => $this->formatAmount($order['amount']),
                'MerchantOrderId'     => $responseData['MerchantOrderId'],
                'TransactionSecurity' => $this->secureTypeMappings[PosInterface::MODEL_3D_SECURE],
            ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @param KuveytPosAccount                     $kuveytPosAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
     * @param CreditCardInterface|null             $creditCard
     *
     * @return array<string, string|int>
     */
    public function create3DEnrollmentCheckRequestData(KuveytPosAccount $kuveytPosAccount, array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($kuveytPosAccount) + [
                'APIVersion'          => self::API_VERSION,
                'HashPassword'        => $this->crypt->hashString($kuveytPosAccount->getStoreKey() ?? ''),
                'TransactionSecurity' => $this->secureTypeMappings[$paymentModel],
                'InstallmentCount'    => $this->mapInstallment($order['installment']),
                'Amount'              => $this->formatAmount($order['amount']),
                'DisplayAmount'       => $this->formatAmount($order['amount']),
                'FECCurrencyCode'     => $this->mapCurrency($order['currency']),
                'MerchantOrderId'     => (string) $order['id'],
                'OkUrl'               => (string) $order['success_url'],
                'FailUrl'             => (string) $order['fail_url'],
            ];

        if ($creditCard instanceof CreditCardInterface) {
            $requestData['CardHolderName']      = (string) $creditCard->getHolderName();
            $requestData['CardNumber']          = $creditCard->getNumber();
            $requestData['CardExpireDateYear']  = $creditCard->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT);
            $requestData['CardExpireDateMonth'] = $creditCard->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT);
            $requestData['CardCVV2']            = $creditCard->getCvv();
        }

        $requestData['HashData'] = $this->crypt->createHash($kuveytPosAccount, $requestData);

        return $requestData;
    }

    /**
     * @phpstan-param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $inputs = $this->getRequestAccountData($posAccount) + [
                'HashPassword'      => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
                'MerchantOrderId'   => $order['id'],
                'OrderId'           => $order['remote_order_id'],
                'CustomerIPAddress' => $order['ip'],
            ];

        $inputs['HashData'] = $this->crypt->createHash($posAccount, $inputs);

        return $inputs;
    }

    /**
     * @phpstan-param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = $this->getRequestAccountData($posAccount) + [
                'APIVersion'          => self::API_VERSION,
                'HashPassword'        => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
                'MerchantOrderId'     => $order['id'],
                'InstallmentCount'    => $this->mapInstallment($order['installment']),
                'Amount'              => $this->formatAmount($order['amount']),
                'FECCurrencyCode'     => $this->mapCurrency($order['currency']),
                'CurrencyCode'        => $this->mapCurrency($order['currency']),
                'TransactionSecurity' => $this->secureTypeMappings[PosInterface::MODEL_NON_SECURE],
                'CardNumber'          => $creditCard->getNumber(),
                'CardExpireDateYear'  => $creditCard->getExpireYear(self::CREDIT_CARD_EXP_YEAR_FORMAT),
                'CardExpireDateMonth' => $creditCard->getExpireMonth(self::CREDIT_CARD_EXP_MONTH_FORMAT),
                'CardCVV2'            => $creditCard->getCvv(),
                'CardHolderName'      => $creditCard->getHolderName(),
            ];

        $inputs['HashData'] = $this->crypt->createHash($posAccount, $inputs);

        return $inputs;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'MerchantOrderId' => $order['id'],
            ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'HashPassword'    => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
                'MerchantOrderId' => $order['id'],
                'OrderId'         => $order['remote_order_id'],
                'PaymentType'     => '1',
            ];

        if (!isset($order['transaction_type']) || PosInterface::TX_TYPE_PAY_PRE_AUTH !== $order['transaction_type']) {
            $result['Amount'] = $this->formatAmount($order['amount']);
        }

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'HashPassword'    => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
                'MerchantOrderId' => $order['id'],
                'OrderId'         => $order['remote_order_id'],
            ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += $this->getRequestAccountData($posAccount) + [
                'APIVersion'   => self::API_VERSION,
                'HashPassword' => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
            ];

        if (!isset($requestData['HashData'])) {
            $requestData['HashData'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $order Vakif Katilim bank'tan donen HTML cevaptan parse edilen form inputlar yada
     *                                     3D Host odemede siparis bilgileri
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $inputs = $order;
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $order = $this->preparePaymentOrder($order);

            $inputs             = [
                'UserName'        => $posAccount->getUsername(),
                'HashPassword'    => $this->crypt->hashString($posAccount->getStoreKey() ?? ''),
                'MerchantId'      => $posAccount->getClientId(),
                'MerchantOrderId' => (string) $order['id'],
                'Amount'          => $this->formatAmount($order['amount']),
                'FECCurrencyCode' => $this->mapCurrency($order['currency']),
                'OkUrl'           => $order['success_url'],
                'FailUrl'         => $order['fail_url'],
                'PaymentType'     => '1',
            ];
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @phpstan-param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $posAccount, array $data = []): array
    {
        $data = $this->prepareHistoryOrder($data);

        $result = $this->getRequestAccountData($posAccount) + [
                /**
                 * Tarih aralığı maksimum 90 gün olabilir.
                 */
                'StartDate'   => $data['start_date']->format('Y-m-d'),
                'EndDate'     => $data['end_date']->format('Y-m-d'),
                'LowerLimit'  => ($data['page'] - 1) * $data['page_size'],
                'UpperLimit'  => $data['page_size'],
                'ProvNumber'  => null,
                'OrderStatus' => null,
                'TranResult'  => null,
                'OrderNo'     => null,
            ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
    }

    /**
     * @phpstan-param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareOrderHistoryOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'StartDate'   => $order['start_date']->format('Y-m-d'),
                'EndDate'     => $order['end_date']->format('Y-m-d'),
                'LowerLimit'  => 0,
                'UpperLimit'  => 100,
                'ProvNumber'  => $order['auth_code'],
                'OrderStatus' => null,
                'TranResult'  => null,
                'OrderNo'     => null,
            ];

        $result['HashData'] = $this->crypt->createHash($posAccount, $result);

        return $result;
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
     * 0 => '0'
     * 1 => '0'
     * 2 => '2'
     * @inheritDoc
     */
    protected function mapInstallment(int $installment): string
    {
        return $installment > 1 ? (string) $installment : '0';
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'id'              => $order['id'],
            'remote_order_id' => $order['remote_order_id'],
            'ip'              => $order['ip'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return \array_merge($order, [
            'id'         => $order['id'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return \array_merge($order, [
            'id'              => $order['id'],
            'remote_order_id' => $order['remote_order_id'],
            'amount'          => $order['amount'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return \array_merge($order, [
            'id'              => $order['id'],
            'remote_order_id' => $order['remote_order_id'],
            'amount'          => $order['amount'],
        ]);
    }

    /**
     * @return array{start_date: \DateTimeInterface, end_date: \DateTimeInterface, page: int, page_size: int}
     *
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $data): array
    {
        return [
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'page'       => $data['page'] ?? 1,
            'page_size'  => $data['page_size'] ?? 10,
        ];
    }

    /**
     * @return array{start_date: \DateTimeInterface, end_date: \DateTimeInterface, auth_code: string}
     *
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return [
            'start_date' => $order['start_date'],
            'end_date'   => $order['end_date'],
            'auth_code' => $order['auth_code'],
        ];
    }

    /**
     * @param KuveytPosAccount $posAccount
     *
     * @return array{MerchantId: string, CustomerId: string, UserName: string, SubMerchantId: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId'    => $posAccount->getClientId(),
            'CustomerId'    => $posAccount->getCustomerId(),
            'UserName'      => $posAccount->getUsername(),
            'SubMerchantId' => $posAccount->getSubMerchantId() ?? '0',
        ];
    }
}
