<?php
/**
 * @license MIT
 */

namespace Mews\Pos\DataMapper\RequestDataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkOdePosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\PosInterface;

/**
 * Creates request data for AkOde Gateway requests
 */
class AkOdePosRequestDataMapper extends AbstractRequestDataMapper
{
    /** @var string */
    public const CREDIT_CARD_EXP_DATE_FORMAT = 'm/y';

    /**
     * {@inheritDoc}
     */
    protected array $txTypeMappings = [
        PosInterface::TX_TYPE_PAY_AUTH      => '1',
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => '2',
        PosInterface::TX_TYPE_PAY_POST_AUTH => '3',
        PosInterface::TX_TYPE_CANCEL        => '4',
        PosInterface::TX_TYPE_REFUND        => '5',
    ];

    /**
     * @param AkOdePosAccount                      $account
     * @param array<string, int|string|float|null> $order
     *
     * @return array<string, string|int>
     */
    public function create3DEnrollmentCheckRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
                'callbackUrl'      => (string) $order['success_url'],
                'orderId'          => (string) $order['id'],
                'amount'           => $this->formatAmount($order['amount']),
                'currency'         => (int) $this->mapCurrency($order['currency']),
                'installmentCount' => (int) $this->mapInstallment($order['installment']),
                'rnd'              => $this->crypt->generateRandomString(),
                'timeSpan'         => $order['timeSpan'],
            ];

        $requestData['hash'] = $this->crypt->create3DHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function create3DPaymentRequestData(AbstractPosAccount $account, array $order, string $txType, array $responseData): array
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePaymentRequestData(AbstractPosAccount $account, array $order, string $txType, CreditCardInterface $card): array
    {
        $order = $this->preparePaymentOrder($order);

        $requestData = [
                'orderId'          => (string) $order['id'],
                'amount'           => $this->formatAmount($order['amount']),
                'currency'         => (int) $this->mapCurrency($order['currency']),
                'installmentCount' => (int) $this->mapInstallment($order['installment']),
                'rnd'              => $this->crypt->generateRandomString(),
                'timeSpan'         => $order['timeSpan'],
                'cardHolderName'   => $card->getHolderName(),
                'cardNo'           => $card->getNumber(),
                'expireDate'       => $card->getExpirationDate('my'),
                'cvv'              => $card->getCvv(),
            ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createNonSecurePostAuthPaymentRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->preparePostPaymentOrder($order);

        $requestData = [
                'orderId'  => (string) $order['id'],
                'amount'   => $this->formatAmount($order['amount']),
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['timeSpan'],
            ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createStatusRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareStatusOrder($order);

        $requestData = [
                'orderId'  => (string) $order['id'],
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['timeSpan'],
            ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createCancelRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareCancelOrder($order);

        $requestData = [
                'orderId'  => (string) $order['id'],
                'rnd'      => $this->crypt->generateRandomString(),
                'timeSpan' => $order['timeSpan'],
            ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createRefundRequestData(AbstractPosAccount $account, array $order): array
    {
        $order = $this->prepareRefundOrder($order);

        $requestData = [
            'orderId'  => (string) $order['id'],
            'rnd'      => $this->crypt->generateRandomString(),
            'amount'   => $this->formatAmount($order['amount']),
            'timeSpan' => $order['timeSpan'],
        ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }

    /**
     * {@inheritDoc}
     */
    public function createHistoryRequestData(AbstractPosAccount $account, array $order, array $extraData = []): array
    {
        $order       = $this->prepareHistoryOrder($order);
        $requestData = [
            'orderId'         => (string) $order['id'],
            'transactionDate' => $order['transactionDate']->format('Ymd'),
            'page'            => $order['page'],
            'pageSize'        => $order['pageSize'],
            'rnd'             => $this->crypt->generateRandomString(),
            'timeSpan'        => $order['timeSpan'],
        ];

        $requestData['hash'] = $this->crypt->createHash($account, $requestData);

        return $this->getRequestAccountData($account) + $requestData;
    }


    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, array $order, string $paymentModel, string $txType, string $gatewayURL, ?CreditCardInterface $card = null): array
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

        if ($card instanceof CreditCardInterface) {
            $inputs['CardHolderName'] = (string) $card->getHolderName();
            $inputs['CardNo']         = $card->getNumber();
            $inputs['ExpireDate']     = $card->getExpireMonth(self::CREDIT_CARD_EXP_DATE_FORMAT);
            $inputs['Cvv']            = $card->getCvv();
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
            'timeSpan'    => $order['timeSpan'] ?? $this->newTimeSpan(),
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
            'timeSpan' => $order['timeSpan'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order): array
    {
        return \array_merge($order, [
            'id'       => $order['id'],
            'timeSpan' => $order['timeSpan'] ?? $this->newTimeSpan(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order): array
    {
        return \array_merge($order, [
            'id'       => $order['id'],
            'timeSpan' => $order['timeSpan'] ?? $this->newTimeSpan(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order): array
    {
        return [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'timeSpan' => $order['timeSpan'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order): array
    {
        return [
            'id'              => $order['id'],
            'transactionDate' => $order['transactionDate'],
            'page'            => $order['page'] ?? 1,
            'pageSize'        => $order['pageSize'] ?? 10,
            'timeSpan'        => $order['timeSpan'] ?? $this->newTimeSpan(),
        ];
    }

    /**
     * @param AbstractPosAccount $account
     *
     * @return array{clientId: string, apiUser: string}
     */
    private function getRequestAccountData(AbstractPosAccount $account): array
    {
        return [
            'clientId' => $account->getClientId(),
            'apiUser'  => $account->getUsername(),
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
