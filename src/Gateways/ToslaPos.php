<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\RequestDataMapper\ToslaPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\ToslaPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Documentation: https://tosla.com/isim-icin/gelistirici-merkezi
 */
class ToslaPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'ToslaPos';

    /** @var ToslaPosAccount */
    protected AbstractPosAccount $account;

    /** @var ToslaPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var ToslaPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH     => [
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH => [
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_3D_HOST,
        ],

        PosInterface::TX_TYPE_HISTORY       => false,
        PosInterface::TX_TYPE_ORDER_HISTORY => true,
        PosInterface::TX_TYPE_PAY_POST_AUTH => true,
        PosInterface::TX_TYPE_CANCEL        => true,
        PosInterface::TX_TYPE_REFUND        => true,
        PosInterface::TX_TYPE_STATUS        => true,
    ];


    /** @return ToslaPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     *
     * @throws UnsupportedTransactionTypeException
     */
    public function getApiURL(string $txType = null, string $paymentModel = null, ?string $orderTxType = null): string
    {
        if (null !== $txType && null !== $paymentModel) {
            return parent::getApiURL().'/'.$this->getRequestURIByTransactionType($txType, $paymentModel);
        }

        return parent::getApiURL();
    }

    /**
     * @inheritDoc
     */
    public function get3DHostGatewayURL(string $threeDSessionId = null): string
    {
        return parent::get3DHostGatewayURL().'/'.$threeDSessionId;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        $request = $request->request;

        if ($this->is3DAuthSuccess($request->all()) && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        $request = $request->request;

        if ($this->is3DAuthSuccess($request->all()) && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($request->all(), $txType, $order);

        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null): array
    {
        if (PosInterface::MODEL_3D_HOST !== $paymentModel && !$creditCard instanceof CreditCardInterface) {
            throw new \LogicException('Kredi kart bilgileri eksik!');
        }

        $data = $this->registerPayment($order, $paymentModel, $txType);

        $status = $data['Code'];

        if (0 !== $status) {
            $this->logger->error('payment register failed', $data);

            throw new \RuntimeException($data['Message'], $data['Code']);
        }

        $this->logger->debug('preparing 3D form data');
        $gatewayUrl = $this->get3DGatewayURL();
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            $gatewayUrl = $this->get3DHostGatewayURL($data['ThreeDSessionId']);
        }

        return $this->requestDataMapper->create3DFormData($this->account, $data, $paymentModel, $txType, $gatewayUrl, $creditCard);
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $paymentModel, string $url): array
    {
        $this->logger->debug('sending request', ['url' => $url]);
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => $contents,
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        if ($response->getStatusCode() === 204) {
            $this->logger->warning('response from api is empty');

            return $this->data = [];
        }

        $responseContent = $response->getBody()->getContents();

        return $this->data = $this->serializer->decode($responseContent, $txType);
    }

    /**
     * Ödeme İşlem Başlatma
     *
     * Ödeme formu ve Ortak Ödeme Sayfası ile ödeme işlemi başlatmak için ThreeDSessionId değeri üretilmelidir.
     * Bu servis 3D secure başlatılması için session açar ve sessionId bilgisini döner.
     * Bu servisten dönen ThreeDSessionId değeri ödeme formunda veya ortak ödeme sayfa çağırma işleminde kullanılır.
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, int|string|float|null> $order
     * @param string                               $paymentModel
     * @param string                               $txType
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
            $order
        );

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

        $requestData = $this->serializer->encode($requestData, $txType);

        return $this->send(
            $requestData,
            $txType,
            $paymentModel,
            $this->getApiURL($txType, $paymentModel)
        );
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
            PosInterface::TX_TYPE_PAY_AUTH      => [
                PosInterface::MODEL_NON_SECURE => 'Payment',
                PosInterface::MODEL_3D_PAY     => 'threeDPayment',
                PosInterface::MODEL_3D_HOST    => 'threeDPayment',
            ],
            PosInterface::TX_TYPE_PAY_PRE_AUTH  => [
                PosInterface::MODEL_3D_PAY  => 'threeDPreAuth',
                PosInterface::MODEL_3D_HOST => 'threeDPreAuth',
            ],
            PosInterface::TX_TYPE_PAY_POST_AUTH => 'postAuth',
            PosInterface::TX_TYPE_CANCEL        => 'void',
            PosInterface::TX_TYPE_REFUND        => 'refund',
            PosInterface::TX_TYPE_STATUS        => 'inquiry',
            PosInterface::TX_TYPE_ORDER_HISTORY => 'history',
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
