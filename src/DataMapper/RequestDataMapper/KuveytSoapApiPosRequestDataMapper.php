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
use Mews\Pos\Gateways\KuveytSoapApiPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for Kuveyt SOAP API Gateway requests
 */
class KuveytSoapApiPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = 'TDV2.0.0';

    /** @var KuveytPosCrypt */
    protected CryptInterface $crypt;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytSoapApiPos::class === $gatewayClass;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        throw new NotImplementedException();
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
        throw new NotImplementedException();
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
        $requestData['VPosMessage'] += $this->getRequestAccountData($posAccount) + [
                'APIVersion' => self::API_VERSION,
            ];

        if (!isset($requestData['VPosMessage']['HashData'])) {
            $requestData['VPosMessage']['HashData'] = $this->crypt->createHash($posAccount, $requestData['VPosMessage']);
        }

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null)
    {
        throw new NotImplementedException();
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
