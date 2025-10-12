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
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class KuveytPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = 'TDV2.0.0';

    /** @var KuveytPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     * @return array{APIVersion: string, HashData: string, CustomerIPAddress: mixed, KuveytTurkVPosAdditionalData: array{AdditionalData: array{Key: string, Data: mixed}}, TransactionType: string, InstallmentCount: mixed, Amount: mixed, DisplayAmount: int, CurrencyCode: mixed, MerchantOrderId: mixed, TransactionSecurity: mixed, MerchantId: string, CustomerId: string, UserName: string}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $result = $this->getRequestAccountData($posAccount) + [
                'APIVersion'                   => self::API_VERSION,
                'HashData'                     => '',
                'CustomerIPAddress'            => $order['ip'],
                'KuveytTurkVPosAdditionalData' => [
                    'AdditionalData' => [
                        'Key'  => 'MD',
                        'Data' => $responseData['MD'],
                    ],
                ],
                'TransactionType'              => $this->valueMapper->mapTxType($txType),
                'InstallmentCount'             => $responseData['VPosMessage']['InstallmentCount'],
                'Amount'                       => $responseData['VPosMessage']['Amount'],
                'DisplayAmount'                => $responseData['VPosMessage']['Amount'],
                'CurrencyCode'                 => $responseData['VPosMessage']['CurrencyCode'],
                'MerchantOrderId'              => $responseData['VPosMessage']['MerchantOrderId'],
                'TransactionSecurity'          => $responseData['VPosMessage']['TransactionSecurity'],
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
     * @return array<string, array<string, string>|int|string|float>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DEnrollmentCheckRequestData(KuveytPosAccount $kuveytPosAccount, array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($kuveytPosAccount) + [
                'APIVersion'          => self::API_VERSION,
                'TransactionType'     => $this->valueMapper->mapTxType($txType),
                'TransactionSecurity' => $this->valueMapper->mapSecureType($paymentModel),
                'InstallmentCount'    => $this->valueFormatter->formatInstallment($order['installment']),
                'Amount'              => (int) $this->valueFormatter->formatAmount($order['amount']),
                //DisplayAmount: Amount değeri ile aynı olacak şekilde gönderilmelidir.
                'DisplayAmount'       => (int) $this->valueFormatter->formatAmount($order['amount']),
                'CurrencyCode'        => $this->valueMapper->mapCurrency($order['currency']),
                'MerchantOrderId'     => (string) $order['id'],
                'OkUrl'               => (string) $order['success_url'],
                'FailUrl'             => (string) $order['fail_url'],
                'DeviceData'          => [
                    'ClientIP' => (string) $order['ip'],
                ],
            ];

        if ($creditCard instanceof CreditCardInterface) {
            $requestData['CardHolderName']      = (string) $creditCard->getHolderName();
            $requestData['CardType']            = $creditCard->getType() !== null ? $this->valueMapper->mapCardType($creditCard->getType()) : '';
            $requestData['CardNumber']          = $creditCard->getNumber();
            $requestData['CardExpireDateYear']  = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'CardExpireDateYear');
            $requestData['CardExpireDateMonth'] = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'CardExpireDateMonth');
            $requestData['CardCVV2']            = $creditCard->getCvv();
        }

        $requestData['HashData'] = $this->crypt->createHash($kuveytPosAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param KuveytPosAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'APIVersion'          => self::API_VERSION,
                'HashData'            => '',
                'TransactionType'     => $this->valueMapper->mapTxType($txType),
                'TransactionSecurity' => '1',
                'MerchantOrderId'     => (string) $order['id'],
                'Amount'              => $this->valueFormatter->formatAmount($order['amount']),
                'DisplayAmount'       => $this->valueFormatter->formatAmount($order['amount']),
                'CurrencyCode'        => $this->valueMapper->mapCurrency($order['currency']),
                'InstallmentCount'    => $this->valueFormatter->formatInstallment($order['installment']),
                'CardHolderName'      => $creditCard->getHolderName(),
                'CardNumber'          => $creditCard->getNumber(),
                'CardExpireDateYear'  => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'CardExpireDateYear'),
                'CardExpireDateMonth' => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'CardExpireDateMonth'),
                'CardCVV2'            => $creditCard->getCvv(),
            ];

        $requestData['HashData'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => null,
            'MailOrTelephoneOrder'  => true,
            'Amount'                => 0,
            'MerchantId'            => $posAccount->getClientId(),
            'MerchantOrderId'       => $order['id'],
            /**
             * Eğer döndüğümüz orderid ile aratılırsa yalnızca aranan işlem gelir.
             * 0 değeri girilirse tarih aralığındaki aynı merchanorderid'ye ait tüm siparişleri getirir.
             * uniq değer orderid'dir, işlemi birebir yakalamak için orderid değeri atanmalıdır.
             */
            'OrderId'               => $order['remote_order_id'] ?? 0,
            /**
             * Test ortamda denendiginde, StartDate ve EndDate her hangi bir tarih atandiginda istek calisiyor,
             * siparisi buluyor.
             * Ancak bu degerler gonderilmediginde veya gecersiz (orn. null) gonderildiginde SOAP server hata donuyor.
             */
            'StartDate'             => $this->valueFormatter->formatDateTime($order['start_date'], 'StartDate'),
            'EndDate'               => $this->valueFormatter->formatDateTime($order['end_date'], 'EndDate'),
            'TransactionType'       => 0,
            'VPosMessage'           => $this->getRequestAccountData($posAccount) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->valueMapper->mapCardType(CreditCardInterface::CARD_TYPE_VISA), //Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => 0,
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => 0,
                    'MerchantOrderId'                  => $order['id'],
                    'CurrencyCode'                     => $this->valueMapper->mapCurrency($order['currency']),
                    'FECAmount'                        => 0,
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($posAccount, $result['VPosMessage']);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => $posAccount->getCustomerId(),
            'MailOrTelephoneOrder'  => true,
            'Amount'                => $this->valueFormatter->formatAmount($order['amount']),
            'MerchantId'            => $posAccount->getClientId(),
            'OrderId'               => $order['remote_order_id'],
            'RRN'                   => $order['ref_ret_num'],
            'Stan'                  => $order['transaction_id'],
            'ProvisionNumber'       => $order['auth_code'],
            'VPosMessage'           => $this->getRequestAccountData($posAccount) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->valueMapper->mapCardType(CreditCardInterface::CARD_TYPE_VISA), //Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => $this->valueFormatter->formatAmount($order['amount']),
                    'DisplayAmount'                    => $this->valueFormatter->formatAmount($order['amount']),
                    'CancelAmount'                     => $this->valueFormatter->formatAmount($order['amount']),
                    'MerchantOrderId'                  => $order['id'],
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => $this->valueMapper->mapCurrency($order['currency']),
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($posAccount, $result['VPosMessage']);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $result = [
            'IsFromExternalNetwork' => true,
            'BusinessKey'           => 0,
            'ResourceId'            => 0,
            'ActionId'              => 0,
            'LanguageId'            => 0,
            'CustomerId'            => $posAccount->getCustomerId(),
            'MailOrTelephoneOrder'  => true,
            'Amount'                => $this->valueFormatter->formatAmount($order['amount']),
            'MerchantId'            => $posAccount->getClientId(),
            'OrderId'               => $order['remote_order_id'],
            'RRN'                   => $order['ref_ret_num'],
            'Stan'                  => $order['transaction_id'],
            'ProvisionNumber'       => $order['auth_code'],
            'VPosMessage'           => $this->getRequestAccountData($posAccount) + [
                    'APIVersion'                       => self::API_VERSION,
                    'InstallmentMaturityCommisionFlag' => 0,
                    'HashData'                         => '',
                    'SubMerchantId'                    => 0,
                    'CardType'                         => $this->valueMapper->mapCardType(CreditCardInterface::CARD_TYPE_VISA), //Default gönderilebilir.
                    'BatchID'                          => 0,
                    'TransactionType'                  => $this->valueMapper->mapTxType($refundTxType),
                    'InstallmentCount'                 => 0,
                    'Amount'                           => $this->valueFormatter->formatAmount($order['amount']),
                    'DisplayAmount'                    => 0,
                    'CancelAmount'                     => $this->valueFormatter->formatAmount($order['amount']),
                    'MerchantOrderId'                  => $order['id'],
                    'FECAmount'                        => 0,
                    'CurrencyCode'                     => $this->valueMapper->mapCurrency($order['currency']),
                    'QeryId'                           => 0,
                    'DebtId'                           => 0,
                    'SurchargeAmount'                  => 0,
                    'SGKDebtAmount'                    => 0,
                    'TransactionSecurity'              => 1,
                ],
        ];

        $result['VPosMessage']['HashData'] = $this->crypt->createHash($posAccount, $result['VPosMessage']);

        return $result;
    }

    /**
     * @param KuveytPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'VPosMessage' => $this->getRequestAccountData($posAccount) + [
                    'APIVersion' => self::API_VERSION,
                ],
        ];

        if (!isset($requestData['VPosMessage']['HashData'])) {
            $requestData['VPosMessage']['HashData'] = $this->crypt->createHash($posAccount, $requestData['VPosMessage']);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, string> $order Kuveyt bank'tan donen HTML cevaptan parse edilen form inputlar
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $order,
        ];
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
    protected function prepareStatusOrder(array $order): array
    {
        return \array_merge($order, [
            'id'         => $order['id'],
            'currency'   => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'start_date' => $order['start_date'] ?? date_create('-360 day'),
            'end_date'   => $order['end_date'] ?? date_create(),
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
            'ref_ret_num'     => $order['ref_ret_num'],
            'auth_code'       => $order['auth_code'],
            'transaction_id'  => $order['transaction_id'],
            'amount'          => $order['amount'],
            'currency'        => $order['currency'] ?? PosInterface::CURRENCY_TRY,
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
            'ref_ret_num'     => $order['ref_ret_num'],
            'auth_code'       => $order['auth_code'],
            'transaction_id'  => $order['transaction_id'],
            'amount'          => $order['amount'],
            'currency'        => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @param KuveytPosAccount $posAccount
     *
     * @return array{MerchantId: string, CustomerId: string, UserName: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'MerchantId' => $posAccount->getClientId(),
            'CustomerId' => $posAccount->getCustomerId(),
            'UserName'   => $posAccount->getUsername(),
        ];
    }
}
