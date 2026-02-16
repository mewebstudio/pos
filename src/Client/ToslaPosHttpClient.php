<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Client;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\Gateways\ToslaPos;
use Mews\Pos\PosInterface;
use Mews\Pos\Serializer\EncodedData;
use Psr\Http\Message\RequestInterface;

class ToslaPosHttpClient extends AbstractHttpClient
{
    /**
     * @inheritDoc
     */
    public static function supports(string $gatewayClass): bool
    {
        return ToslaPos::class === $gatewayClass;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     * @throws \InvalidArgumentException when transaction type or payment model are not provided
     */
    public function getApiURL(?string $txType = null, ?string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType && null !== $paymentModel) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType, $paymentModel);
        }

        throw new \InvalidArgumentException('Transaction type and payment model are required to generate API URL');
    }

    /**
     * @inheritDoc
     */
    protected function createRequest(string $url, EncodedData $content, string $txType, ?AbstractPosAccount $account = null): RequestInterface
    {
        $body = $this->streamFactory->createStream($content->getData());

        $request = $this->requestFactory->createRequest('POST', $url);

        return $request->withHeader('Content-Type', 'application/json')
            ->withBody($body);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     * @phpstan-param PosInterface::MODEL_*   $paymentModel
     *
     * @return string
     *
     * @throws UnsupportedTransactionTypeException
     */
    private function getRequestURIByTransactionType(string $txType, string $paymentModel): string
    {
        $arr = [
            PosInterface::TX_TYPE_PAY_AUTH       => [
                PosInterface::MODEL_NON_SECURE => 'Payment',
                PosInterface::MODEL_3D_PAY     => 'threeDPayment',
                PosInterface::MODEL_3D_HOST    => 'threeDPayment',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
                PosInterface::MODEL_3D_PAY  => 'threeDPreAuth',
                PosInterface::MODEL_3D_HOST => 'threeDPreAuth',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH  => 'postAuth',
            PosInterface::TX_TYPE_CANCEL         => 'void',
            PosInterface::TX_TYPE_REFUND         => 'refund',
            PosInterface::TX_TYPE_REFUND_PARTIAL => 'refund',
            PosInterface::TX_TYPE_STATUS         => 'inquiry',
            PosInterface::TX_TYPE_ORDER_HISTORY  => 'history',
        ];

        if (!isset($arr[$txType])) {
            throw new UnsupportedTransactionTypeException();
        }

        if (\is_string($arr[$txType])) {
            return $arr[$txType];
        }

        if (!isset($arr[$txType][$paymentModel])) {
            throw new UnsupportedTransactionTypeException();
        }

        return $arr[$txType][$paymentModel];
    }
}
