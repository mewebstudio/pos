<?php

/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for Tosla Gateway requests
 */
class ToslaPosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'm/y';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH       => '1',
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => '2',
        PosInterface::TX_TYPE_PAY_POST_AUTH  => '3',
        PosInterface::TX_TYPE_CANCEL         => '4',
        PosInterface::TX_TYPE_REFUND         => '5',
        PosInterface::TX_TYPE_REFUND_PARTIAL => '5',
    ];

    /**
     * @param ToslaPosAccount                      $posAccount
     * @param array<string, int|string|float|null> $order
     *
     * @return array<string, string|int>
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'callbackUrl'      => (string) $order['success_url'],
                'orderId'          => (string) $order['id'],
                'amount'           => $this->formatAmount($order['amount']),
                'currency'         => (int) $this->mapCurrency($order['currency']),
                'installmentCount' => (int) $this->mapInstallment($order['installment']),
                'rnd'              => $this->crypt->generateRandomString(),
                'timeSpan'         => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
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
    public function createNonSecurePaymentRequestData(AbstractPosAccount $posAccount, array $order, string $txType, CreditCardInterface $creditCard): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'          => (string) $order['id'],
                'amount'           => $this->formatAmount($order['amount']),
                'currency'         => (int) $this->mapCurrency($order['currency']),
                'installmentCount' => (int) $this->mapInstallment($order['installment']),
                'rnd'              => $this->crypt->generateRandomString(),
                'timeSpan'         => $order['time_span'],
                'cardHolderName'   => $creditCard->getHolderName(),
                'cardNo'           => $creditCard->getNumber(),
                'expireDate'       => $creditCard->getExpirationDate('my'),
                'cvv'              => $creditCard->getCvv(),
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'  => (string) $order['id'],
                'amount'   => $this->formatAmount($order['amount']),
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'  => (string) $order['id'],
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'  => (string) $order['id'],
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $posAccount, array $order, string $refundTxType): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'  => (string) $order['id'],
                'rnd'      => $this->crypt->generateRandomString(),
                'amount'   => $this->formatAmount($order['amount']),
                'timeSpan' => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createOrderHistoryRequestData(AbstractPosAccount $posAccount, array $order): array
    {
        $order       = $this->prepareOrderHistoryOrder($order);
        $requestData = $this->getRequestAccountData($posAccount) + [
                'orderId'         => (string) $order['id'],
                'transactionDate' => $order['transaction_date']->format('Ymd'),
                'page'            => $order['page'],
                'pageSize'        => $order['page_size'],
                'rnd'             => $this->crypt->generateRandomString(),
                'timeSpan'        => $order['time_span'],
            ];

        $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);

        return $requestData;
    }

    /**
     * @inheritDoc
     */
    public function createCustomQueryRequestData(AbstractPosAccount $posAccount, array $requestData): array
    {
        $requestData += $this->getRequestAccountData($posAccount) + [
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $this->newTimeSpan(),
            ];

        if (!isset($requestData['hash'])) {
            $requestData['hash'] = $this->crypt->createHash($posAccount, $requestData);
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
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function create3DFormData(AbstractPosAccount $posAccount, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $creditCard = null): array
    {
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return [
                'gateway' => $gatewayURL,
                'method'  => 'GET',
                'inputs'  => [],
            ];
        }

        $inputs = [
            'ThreeDSessionId' => (string) $order['ThreeDSessionId'],
        ];

        if ($creditCard instanceof CreditCardInterface) {
            $inputs['CardHolderName'] = (string) $creditCard->getHolderName();
            $inputs['CardNo']         = $creditCard->getNumber();
            $inputs['ExpireDate']     = $creditCard->getExpirationDate(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv']            = $creditCard->getCvv();
        }

        return [
            'gateway' => $gatewayURL,
            'method'  => 'POST',
            'inputs'  => $inputs,
        ];
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
     * Get amount
     * formats 10.01 to 1001
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
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order): array
    {
        return \array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? PosInterface::CURRENCY_TRY,
            'time_span'   => $order['time_span'] ?? $this->newTimeSpan(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order): array
    {
        return [
            'id'        => $order['id'],
            'amount'    => $order['amount'],
            'time_span' => $order['time_span'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return \array_merge($order, [
            'id'        => $order['id'],
            'time_span' => $order['time_span'] ?? $this->newTimeSpan(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return \array_merge($order, [
            'id'        => $order['id'],
            'time_span' => $order['time_span'] ?? $this->newTimeSpan(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'id'        => $order['id'],
            'amount'    => $order['amount'],
            'time_span' => $order['time_span'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareOrderHistoryOrder(array $order): array
    {
        return [
            'id'               => $order['id'],
            'transaction_date' => $order['transaction_date'],
            'page'             => $order['page'] ?? 1,
            'page_size'        => $order['page_size'] ?? 10,
            'time_span'        => $order['time_span'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @param AbstractPosAccount $posAccount
     *
     * @return array{clientId: string, apiUser: string}
     */
    private function getRequestAccountData(AbstractPosAccount $posAccount): array
    {
        return [
            'clientId' => $posAccount->getClientId(),
            'apiUser'  => $posAccount->getUsername(),
        ];
    }

    /**
     * @return string ex: 20231209201121
     */
    private function newTimeSpan(): string
    {
        $turkeyTimeZone = new \DateTimeZone('Europe/Istanbul');
        $turkeyTime     = new \DateTime('now', $turkeyTimeZone);

        return $turkeyTime->format('YmdHis');
    }
}
