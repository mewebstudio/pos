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
use Mews\Pos\Gateways\KuveytPos;
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
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return KuveytPos::class === $gatewayClass;
    }

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
     * @param KuveytPosAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += $this->getRequestAccountData($posAccount) + [
                    'APIVersion' => self::API_VERSION,
        ];

        if (!isset($requestData['HashData'])) {
            $requestData['HashData'] = $this->crypt->createHash($posAccount, $requestData);
        }

        return $requestData;
    }

    /**
     * Küveyt Türk kendisi hazır HTML form gönderiyor.
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
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
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
