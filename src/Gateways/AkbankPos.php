<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * https://sanalpos-prep.akbank.com/#entry
 */
class AkbankPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'AkbankPos';

    /** @var AkbankPosAccount */
    protected AbstractPosAccount $account;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH      => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH  => true,
        PosInterface::TX_TYPE_PAY_POST_AUTH => true,
        PosInterface::TX_TYPE_STATUS        => false,
        PosInterface::TX_TYPE_CANCEL        => true,
        PosInterface::TX_TYPE_REFUND        => true,
        PosInterface::TX_TYPE_ORDER_HISTORY => true,
        PosInterface::TX_TYPE_HISTORY       => true,
    ];

    /**
     * @inheritDoc
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType);
        }

        return parent::getApiURL();
    }

    /** @return AkbankPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $request = $request->request;

        if (!$this->is3DAuthSuccess($request->all())) {
            $this->response = $this->responseDataMapper->map3DPaymentData(
                $request->all(),
                null,
                $txType,
                $order
            );

            return $this;
        }

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $contents          = $this->serializer->encode($requestData, $txType);
        $provisionResponse = $this->send(
            $contents,
            $txType,
            PosInterface::MODEL_3D_SECURE,
            $this->getApiURL($txType)
        );

        $this->response = $this->responseDataMapper->map3DPaymentData(
            $request->all(),
            $provisionResponse,
            $txType,
            $order
        );
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all(), $txType, $order);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null): array
    {
        $this->logger->debug('preparing 3D form data');

        $gatewayUrl = PosInterface::MODEL_3D_HOST === $paymentModel ? $this->get3DHostGatewayURL() : $this->get3DGatewayURL();

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $gatewayUrl,
            $creditCard
        );
    }

    /**
     * @inheritDoc
     */
    public function status(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     *
     * @throws \RuntimeException thrown when we get HTTP 400 error
     */
    protected function send($contents, string $txType, string $paymentModel, string $url): array
    {
        $this->logger->debug('sending request', ['url' => $url]);
        if (!\is_string($contents)) {
            throw new \InvalidArgumentException(\sprintf('Argument type must be string, %s provided.', \gettype($contents)));
        }

        $hash = $this->requestDataMapper->getCrypt()->hashString($contents, $this->account->getStoreKey());

        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'auth-hash' => $hash,
            ],
            'body'    => $contents,
        ]);

        if ($response->getStatusCode() === 400) {
            $this->logger->error('api error', ['status_code' => $response->getStatusCode()]);

            // when the data is sent fails validation checks we get 400 error
            $data = $this->serializer->decode($response->getBody()->getContents(), $txType);
            throw new \RuntimeException($data['message'], $data['code']);
        }

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }

    /**
     * @phpstan-param PosInterface::TX_TYPE_* $txType
     *
     * @param string $txType
     *
     * @return string
     */
    private function getRequestURIByTransactionType(string $txType): string
    {
        $arr = [
            PosInterface::TX_TYPE_HISTORY => 'portal/report/transaction',
        ];

        return $arr[$txType] ?? 'transaction/process';
    }
}
