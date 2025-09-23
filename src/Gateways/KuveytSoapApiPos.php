<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\KuveytPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\KuveytPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Kuveyt bankın SOAP API'nı desteleyen Gateway
 */
class KuveytSoapApiPos extends AbstractSoapGateway
{
    /** @var string */
    public const NAME = 'KuveytSoapApiPos';

    /** @var KuveytPosAccount */
    protected AbstractPosAccount $account;

    /** @var KuveytPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var KuveytPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => false,
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => false,
        PosInterface::TX_TYPE_PAY_POST_AUTH  => false,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return KuveytPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * Kuveyt bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true)
    {
        throw new UnsupportedPaymentModelException('Bu işlem için KuveytPos gateway kullanılmalıdır.');
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        throw new UnsupportedPaymentModelException('Bu işlem için KuveytPos gateway kullanılmalıdır.');
    }

    /**
     * @inheritDoc
     */
    protected function send(array $data, string $txType, string $url): array
    {
        $this->logger->debug('sending soap request', [
            'txType' => $txType,
            'url'    => $url,
        ]);

        $result = $this->client->call(
            $url,
            $data['VPosMessage']['TransactionType'],
            ['parameters' => ['request' => $data]]
        );

        return $this->data = $result;
    }
}
