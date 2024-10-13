<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for KuveytPos Gateway requests
 */
class InterPosRequestDataMapper extends AbstractRequestDataMapper
{
    /**
     * MOTO (Mail Order Telephone Order) 0 for false, 1 for true
     * @var string
     */
    protected const MOTO = '0';

    /**
     *  TODO tekrarlanan odemeler icin daha fazla bilgi lazim, Deniz bank dokumantasyonunda hic bir aciklama yok
     *  ornek kodlarda ise sadece bu alttaki 2 veriyi gondermis.
     * 'MaturityPeriod' => 1,
     * 'PaymentFrequency' => 2,
     *
     * {@inheritDoc}
     *
     * @param array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $responseData
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'                 => $this->valueMapper->mapTxType($txType),
                'SecureType'              => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'OrderId'                 => (string) $order['id'],
                'PurchAmount'             => $this->valueFormatter->formatAmount($order['amount']),
                'Currency'                => $this->valueMapper->mapCurrency($order['currency']),
                'InstallmentCount'        => $this->valueFormatter->formatInstallment($order['installment']),
                'MD'                      => $responseData['MD'],
                'PayerTxnId'              => $responseData['PayerTxnId'],
                'Eci'                     => $responseData['Eci'],
                'PayerAuthenticationCode' => $responseData['PayerAuthenticationCode'],
                'MOTO'                    => self::MOTO,
                'Lang'                    => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'          => $this->valueMapper->mapTxType($txType),
                'SecureType'       => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'OrderId'          => $order['id'],
                'PurchAmount'      => $this->valueFormatter->formatAmount($order['amount']),
                'Currency'         => $this->valueMapper->mapCurrency($order['currency']),
                'InstallmentCount' => $this->valueFormatter->formatInstallment($order['installment']),
                'MOTO'             => self::MOTO,
                'Lang'             => $this->getLang($posAccount, $order),
                'CardType'         => $creditCard->getType() !== null ? $this->valueMapper->mapCardType($creditCard->getType()) : null,
                'Pan'              => $creditCard->getNumber(),
                'Expiry'           => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'Expiry'),
                'Cvv2'             => $creditCard->getCvv(),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{TxnType: string, SecureType: string, OrderId: null, orgOrderId: mixed, PurchAmount: mixed, Currency: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'TxnType'     => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_PAY_POST_AUTH),
                'SecureType'  => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'OrderId'     => null,
                'orgOrderId'  => (string) $order['id'],
                'PurchAmount' => $this->valueFormatter->formatAmount($order['amount']),
                'Currency'    => (string) $this->valueMapper->mapCurrency($order['currency']),
                'MOTO'        => self::MOTO,
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order['id'],
                'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_STATUS),
                'SecureType' => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'Lang'       => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, TxnType: string, SecureType: string, Lang: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'    => null, //todo buraya hangi deger verilecek?
                'orgOrderId' => (string) $order['id'],
                'TxnType'    => $this->valueMapper->mapTxType(PosInterface::TX_TYPE_CANCEL),
                'SecureType' => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'Lang'       => $this->getLang($posAccount, $order),
            ];
    }

    /**
     * {@inheritDoc}
     * @return array{OrderId: null, orgOrderId: string, PurchAmount: string, TxnType: string, SecureType: string, Lang: string, MOTO: string, UserCode: string, UserPass: string, ShopCode: string}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        return $this->getRequestAccountData($posAccount) + [
                'OrderId'     => null,
                'orgOrderId'  => (string) $order['id'],
                'PurchAmount' => (string) $this->valueFormatter->formatAmount($order['amount']),
                'TxnType'     => $this->valueMapper->mapTxType($refundTxType),
                'SecureType'  => $this->valueMapper->mapSecureType(PosInterface::MODEL_NON_SECURE),
                'Lang'        => $this->getLang($posAccount, $order),
                'MOTO'        => self::MOTO,
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
     * {@inheritDoc}
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'ShopCode'         => $posAccount->getClientId(),
            'TxnType'          => $this->valueMapper->mapTxType($txType),
            'SecureType'       => $this->valueMapper->mapSecureType($paymentModel),
            'PurchAmount'      => (string) $this->valueFormatter->formatAmount($order['amount']),
            'OrderId'          => (string) $order['id'],
            'OkUrl'            => (string) $order['success_url'],
            'FailUrl'          => (string) $order['fail_url'],
            'Rnd'              => $this->crypt->generateRandomString(),
            'Lang'             => $this->getLang($posAccount, $order),
            'Currency'         => (string) $this->valueMapper->mapCurrency($order['currency']),
            'InstallmentCount' => (string) $this->valueFormatter->formatInstallment($order['installment']),
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['CardType'] = $creditCard->getType() !== null ? $this->valueMapper->mapCardType($creditCard->getType()) : '';
            $inputs['Pan']      = $creditCard->getNumber();
            $inputs['Expiry']   = $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'Expiry');
            $inputs['Cvv2']     = $creditCard->getCvv();
        }

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            InterPos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Hash'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        return $requestData + $this->getRequestAccountData($posAccount);
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     *
     * @return array{UserCode: string, UserPass: string, ShopCode: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'UserCode' => $posAccount->getUsername(),
            'UserPass' => $posAccount->getPassword(),
            'ShopCode' => $posAccount->getClientId(),
        ];
    }
}
