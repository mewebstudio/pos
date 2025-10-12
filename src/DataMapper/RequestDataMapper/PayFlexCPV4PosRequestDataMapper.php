<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PayFlex Common Payment V4 Gateway requests
 */
class PayFlexCPV4PosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * {@inheritDoc}
     *
     * @param PayFlexAccount $posAccount
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData, ?CreditCardInterface $creditCard = null): array
    {
        throw new NotImplementedException();
    }


    /**
     * @param PayFlexAccount                                     $posAccount
     * @param array{TransactionId: string, PaymentToken: string} $responseData
     *
     * @return array{HostMerchantId: string, Password: string, TransactionId: string, PaymentToken: string}
     */
    public function create3DPaymentStatusRequestData(AbstractPosAccount $posAccount, array $responseData): array
    {
        return $this->getRequestAccountData($posAccount) + [
                'TransactionId' => $responseData['TransactionId'],
                'PaymentToken'  => $responseData['PaymentToken'],
            ];
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param PayFlexAccount                       $posAccount
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param string                               $paymentModel
     * @param CreditCardInterface|null             $creditCard
     *
     * @return array<string, string>
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order, string $txType, string $paymentModel, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'HostMerchantId'       => $posAccount->getClientId(),
            'MerchantPassword'     => $posAccount->getPassword(),
            'HostTerminalId'       => $posAccount->getTerminalId(),
            'TransactionType'      => $this->valueMapper->mapTxType($txType),
            'AmountCode'           => (string) $this->valueMapper->mapCurrency($order['currency']),
            'Amount'               => (string) $this->valueFormatter->formatAmount($order['amount']),
            'OrderID'              => (string) $order['id'],
            'IsSecure'             => 'true', // Işlemin 3D yapılıp yapılmayacağına dair flag, alabileceği değerler: 'true', 'false'
            /**
             * 3D Programına Dahil Olmayan Kartlar ile İşlem Yapma Flagi: "3D İşlem Flagi" (IsSecure) "true" gönderilmiş
             * işlemler için bir alt seçenektir. Kart sahibi "3D Secure" programına dahil değilse Ortak Ödemenin işlemi
             * Sanal Pos'a gönderip göndermeyeceğini belirtir. "true" gönderilmesi durumunda kart sahibi
             * 3D Secure programına dahil olmasa bile işlemi Sanal Pos'a gönderecektir.
             * Bu tür işlemler "Half Secure" olarak işaretlenecektir.
             */
            'AllowNotEnrolledCard' => 'false',
            'SuccessUrl'           => (string) $order['success_url'],
            'FailUrl'              => (string) $order['fail_url'],
            'RequestLanguage'      => $this->getLang($posAccount, $order),
            /**
             * Bu alanda gönderilecek değer kart hamili
             * ektresinde işlem açıklamasında çıkacaktır.
             * (Abone no vb. bilgiler gönderilebilir)
             */
            'Extract'              => '',
            /**
             * Uye işyeri tarafından işleme ait ek bilgiler varsa CustomItems alanında gönderilir.
             * İçeriğinde "name" ve "value" attirbutelarını barındırır.
             * Örnek: İsim1:Değer1 İsim2:Değer2 İsim3:Değer3
             */
            'CustomItems'          => '',
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $requestData += [
                'BrandNumber'     => $creditCard->getType() !== null ? $this->valueMapper->mapCardType($creditCard->getType()) : '',
                'CVV'             => $creditCard->getCvv(),
                'PAN'             => $creditCard->getNumber(),
                'ExpireMonth'     => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'ExpireMonth'),
                'ExpireYear'      => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'ExpireYear'),
                'CardHoldersName' => (string) $creditCard->getHolderName(),
            ];
        }

        if ($order['installment']) {
            $requestData['InstallmentCount'] = (string) $this->valueFormatter->formatInstallment($order['installment']);
        }

        $requestData['HashedData'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
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
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        throw new NotImplementedException();
    }

    /**
     * @param PayFlexAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
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
     * {@inheritDoc}
     *
     * @param array<string, int|string|float|null>|null             $order kullanilmiyor
     * @param array{CommonPaymentUrl: string, PaymentToken: string} $extraData
     *
     * @return array{gateway: string, method: 'GET', inputs: array{Ptkn: string}}
     */
    public function create3DFormData(
        ?AbstractPosAccount  $posAccount,
        ?array               $order,
        ?string              $paymentModel,
        ?string              $txType,
        ?string              $gatewayURL,
        ?CreditCardInterface $creditCard = null,
        array                $extraData = []
    ): array {
        return [
            'gateway' => $extraData['CommonPaymentUrl'],
            'method'  => 'GET',
            'inputs'  => [
                'Ptkn' => $extraData['PaymentToken'],
            ],
        ];
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
        ]);
    }

    /**
     * @param PayFlexAccount $posAccount
     *
     * @return array{HostMerchantId: string, Password: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'HostMerchantId' => $posAccount->getClientId(),
            'Password'       => $posAccount->getPassword(),
        ];
    }
}
