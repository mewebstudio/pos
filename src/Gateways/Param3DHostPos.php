<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\Param3DHostPosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ParamPosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @since 2.0.0
 * Documentation:
 * @link https://dev.param.com.tr
 */
class Param3DHostPos extends AbstractHttpGateway
{
    /** @var string */
    public const NAME = 'Param3DHostPos';

    /** @var ParamPosAccount */
    protected AbstractPosAccount $account;

    /** @var Param3DHostPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var ParamPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH     => [
            PosInterface::MODEL_3D_HOST,
        ],
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_PAY_POST_AUTH  => false,
        PosInterface::TX_TYPE_CANCEL         => false,
        PosInterface::TX_TYPE_REFUND         => false,
        PosInterface::TX_TYPE_REFUND_PARTIAL => false,
        PosInterface::TX_TYPE_STATUS         => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => false,
    ];

    /**
     * @return ParamPosAccount
     */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, ?CreditCardInterface $creditCard = null): PosInterface
    {
        throw new UnsupportedPaymentModelException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        if (
            !$this->is3DHashCheckDisabled()
            && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())
        ) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPayment(array $order, CreditCardInterface $creditCard, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function makeRegularPostPayment(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?CreditCardInterface $creditCard = null, bool $createWithoutCard = true)
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard);

        $data = $this->registerPayment($order, $paymentModel, $txType);

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            null,
            $data
        );
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function cancel(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function refund(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function customQuery(array $requestData, ?string $apiUrl = null): PosInterface
    {
        throw new UnsupportedTransactionTypeException(
            \sprintf('Bu işlem için %s gateway kullanılmalıdır.', ParamPos::class)
        );
    }

    /**
     * @param array<string, int|string|float|null>                              $order
     * @param PosInterface::MODEL_3D_*                                          $paymentModel
     * @param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     *
     * @return array<string, mixed>
     *
     * @throws UnsupportedTransactionTypeException
     * @throws ClientExceptionInterface
     */
    private function registerPayment(array $order, string $paymentModel, string $txType): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $txType
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        return $this->clientStrategy->getClient(
            $txType,
            $paymentModel,
        )->request(
            $txType,
            $paymentModel,
            $requestData,
            $order
        );
    }
}
