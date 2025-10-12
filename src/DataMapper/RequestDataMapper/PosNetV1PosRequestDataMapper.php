<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\DataMapper\RequestValueFormatter\PosNetV1PosRequestValueFormatter;
use Mews\Pos\DataMapper\RequestValueFormatter\RequestValueFormatterInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\Before3DFormHashCalculatedEvent;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Gateways\PosNetV1Pos;
use Mews\Pos\PosInterface;

/**
 * Creates request data for PosNetV1Pos Gateway requests
 */
class PosNetV1PosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const API_VERSION = 'V100';

    /**
     * @var PosNetV1PosRequestValueFormatter
     */
    protected RequestValueFormatterInterface $valueFormatter;

    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return PosNetV1Pos::class === $gatewayClass;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, array $responseData): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'ApiType'               => 'JSON',
            'ApiVersion'            => self::API_VERSION,
            'MerchantNo'            => $posAccount->getClientId(),
            'TerminalNo'            => $posAccount->getTerminalId(),
            'PaymentInstrumentType' => 'CARD',
            'IsEncrypted'           => 'N',
            'IsTDSecureMerchant'    => 'Y',
            'IsMailOrder'           => 'N',
            'ThreeDSecureData'      => [
                'SecureTransactionId' => $responseData['SecureTransactionId'],
                'CavvData'            => $responseData['CAVV'],
                'Eci'                 => $responseData['ECI'],
                'MdStatus'            => (int) $responseData['MdStatus'],
                'MD'                  => $responseData['MD'],
            ],
            'MACParams'             => 'MerchantNo:TerminalNo:SecureTransactionId:CavvData:Eci:MdStatus',
            'Amount'                => $this->valueFormatter->formatAmount($order['amount']),
            'CurrencyCode'          => $this->valueMapper->mapCurrency($order['currency']),
            'PointAmount'           => 0,
            'OrderId'               => $this->valueFormatter->formatOrderId($order['id']),
            'InstallmentCount'      => $this->valueFormatter->formatInstallment($order['installment']),
            'InstallmentType'       => 'N',
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        $requestData['MAC'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'AdditionalInfoData'     => null,
            'CardInformationData'    => [
                'CardNo'         => $creditCard->getNumber(),
                'ExpireDate'     => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'ExpireDate'),
                'Cvc2'           => $creditCard->getCvv(),
                'CardHolderName' => $creditCard->getHolderName(),
            ],
            'IsMailOrder'            => 'N',
            'IsRecurring'            => null,
            'IsTDSecureMerchant'     => null,
            'PaymentInstrumentType'  => 'CARD',
            'ThreeDSecureData'       => null,
            'Amount'                 => $this->valueFormatter->formatAmount($order['amount']),
            'CurrencyCode'           => $this->valueMapper->mapCurrency($order['currency']),
            'OrderId'                => $this->valueFormatter->formatOrderId($order['id']),
            'InstallmentCount'       => $this->valueFormatter->formatInstallment($order['installment']),
            'InstallmentType'        => 'N',
            'KOICode'                => null,
            'MerchantMessageData'    => null,
            'PointAmount'            => null,
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MACParams'              => 'MerchantNo:TerminalNo',
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'Amount'                 => $this->valueFormatter->formatAmount($order['amount']),
            'CurrencyCode'           => $this->valueMapper->mapCurrency($order['currency']),
            'ReferenceCode'          => $order['ref_ret_num'],
            'InstallmentCount'       => $this->valueFormatter->formatInstallment($order['installment']),
            'InstallmentType'        => 'N',
        ];

        if ($order['installment'] > 1) {
            $requestData['InstallmentType'] = 'Y';
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => 'N',
            'PaymentFacilitatorData' => null,
            'OrderId'                => $this->valueFormatter->formatOrderId($order['id'], PosInterface::TX_TYPE_STATUS, $order['payment_model']),
        ];
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->valueMapper->mapTxType($order['transaction_type']),
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData['ReferenceCode'] = $order['ref_ret_num'];
        } else {
            $requestData['OrderId'] = $this->valueFormatter->formatOrderId($order['id'], PosInterface::TX_TYPE_CANCEL, $order['payment_model']);
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = [
            'ApiType'                => 'JSON',
            'ApiVersion'             => self::API_VERSION,
            'MerchantNo'             => $posAccount->getClientId(),
            'TerminalNo'             => $posAccount->getTerminalId(),
            'MACParams'              => 'MerchantNo:TerminalNo:ReferenceCode:OrderId',
            'CipheredData'           => null,
            'DealerData'             => null,
            'IsEncrypted'            => null,
            'PaymentFacilitatorData' => null,
            'ReferenceCode'          => null,
            'OrderId'                => null,
            'TransactionType'        => $this->valueMapper->mapTxType($order['transaction_type']),
        ];

        if (isset($order['ref_ret_num'])) {
            $requestData['ReferenceCode'] = $order['ref_ret_num'];
        } else {
            $requestData['OrderId'] = $this->valueFormatter->formatOrderId($order['id'], $refundTxType, $order['payment_model']);
        }

        if ($order['payment_model'] === PosInterface::MODEL_NON_SECURE) {
            $requestData['Amount']       = $this->valueFormatter->formatAmount($order['amount']);
            $requestData['CurrencyCode'] = $this->valueMapper->mapCurrency($order['currency']);
        }

        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');

        return $requestData;
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += [
            'ApiType'    => 'JSON',
            'ApiVersion' => self::API_VERSION,
            'MerchantNo' => $posAccount->getClientId(),
            'TerminalNo' => $posAccount->getTerminalId(),
        ];

        if (!isset($requestData['MAC'])) {
            if (null === $posAccount->getStoreKey()) {
                throw new \LogicException('Account storeKey eksik!');
            }

            $requestData['MAC'] = $this->crypt->hashFromParams($posAccount->getStoreKey(), $requestData, 'MACParams', ':');
        }

        return $requestData;
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
     * @param PosNetAccount $posAccount
     *
     * @return array{gateway: string, method: 'POST', inputs: array<string, string>}
     *
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null, $extraData = null): array
    {
        $order = $this->preparePaymentOrder($order);

        $inputs = [
            'MerchantNo'        => $posAccount->getClientId(),
            'TerminalNo'        => $posAccount->getTerminalId(),
            'PosnetID'          => $posAccount->getPosNetId(),
            'TransactionType'   => $this->valueMapper->mapTxType($txType),
            'OrderId'           => $this->valueFormatter->formatOrderId($order['id']),
            'Amount'            => (string) $this->valueFormatter->formatAmount($order['amount']),
            'CurrencyCode'      => (string) $this->valueMapper->mapCurrency($order['currency']),
            'MerchantReturnURL' => (string) $order['success_url'],
            'InstallmentCount'  => $this->valueFormatter->formatInstallment($order['installment']),
            'Language'          => $this->getLang($posAccount, $order),
            'TxnState'          => 'INITIAL',
            'OpenNewWindow'     => '0',
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $cardData = [
                'CardNo'         => $creditCard->getNumber(),
                // Kod calisiyor ancak burda bir tutarsizlik var: ExpireDate vs ExpiredDate
                // MacParams icinde ExpireDate olarak geciyor, gonderidigimizde ise ExpiredDate olarak istiyor.
                'ExpiredDate'    => $this->valueFormatter->formatCardExpDate($creditCard->getExpirationDate(), 'ExpiredDate'),
                'Cvv'            => $creditCard->getCvv(),
                'CardHolderName' => (string) $creditCard->getHolderName(),

                'MacParams' => 'MerchantNo:TerminalNo:CardNo:Cvc2:ExpireDate:Amount',
                'UseOOS'    => '0',
            ];
        } else {
            $cardData = [
                /**
                 * UseOOS alanını 1 yaparak bankanın ortak ödeme sayfasının açılmasını ve
                 * bu ortak ödeme sayfası ile müşterinin kart bilgilerini girmesini sağlatabilir.
                 */
                'UseOOS'    => '1',
                'MacParams' => 'MerchantNo:TerminalNo:Amount',
            ];
        }

        $inputs += $cardData;

        $event = new Before3DFormHashCalculatedEvent(
            $inputs,
            $posAccount->getBank(),
            $txType,
            $paymentModel,
            PosNetV1Pos::class
        );
        $this->eventDispatcher->dispatch($event);
        $inputs = $event->getFormInputs();

        $inputs['Mac'] = $this->crypt->create3DHash($posAccount, $inputs);

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return array_merge($order, [
            'id'          => $order['id'],
            'installment' => $order['installment'] ?? 0,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'          => $order['id'],
            'amount'      => $order['amount'],
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'ref_ret_num' => $order['ref_ret_num'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return [
            'id'            => $order['id'],
            'payment_model' => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? PosInterface::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? PosInterface::TX_TYPE_PAY_AUTH,
            'amount'           => $order['amount'],
            'currency'         => $order['currency'] ?? PosInterface::CURRENCY_TRY,
        ];
    }
}
