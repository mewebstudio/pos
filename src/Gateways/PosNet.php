<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Exception;
use InvalidArgumentException;
use LogicException;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;
use function gettype;
use function sprintf;

/**
 * Class PosNet
 */
class PosNet extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PosNet';

    /** @var PosNetAccount */
    protected AbstractPosAccount $account;

    /** @var PosNetRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PosNetResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY      => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PRE_PAY  => true,
        PosInterface::TX_TYPE_POST_PAY => true,
        PosInterface::TX_TYPE_STATUS   => true,
        PosInterface::TX_TYPE_CANCEL   => true,
        PosInterface::TX_TYPE_REFUND   => true,
        PosInterface::TX_TYPE_HISTORY  => false,
    ];

    /**
     * Get OOS transaction data
     * siparis bilgileri ve kart bilgilerinin şifrelendiği adımdır.
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY|PosInterface::TX_TYPE_PRE_PAY $txType
     *
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param CreditCardInterface                  $card
     *
     * @return array{approved: string, respCode: string, respText: string, oosRequestDataResponse?: array{data1: string, data2: string, sign: string}}
     */
    public function getOosTransactionData(array $order, string $txType, CreditCardInterface $card): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $txType, $card);

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

        $xml         = $this->serializer->encode($requestData, $txType);

        return $this->send($xml, $txType, PosInterface::MODEL_3D_SECURE);
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $card = null): PosInterface
    {
        $request = $request->request;

        $this->logger->debug('getting merchant request data');
        $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
            $this->account,
            $order,
            $request->all()
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

        $contents           = $this->serializer->encode($requestData, $txType);
        $userVerifyResponse = $this->send($contents, $txType, PosInterface::MODEL_3D_SECURE);
        $bankResponse       = null;

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $userVerifyResponse['approved']) {
            goto end;
        }

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $userVerifyResponse['oosResolveMerchantDataResponse'])) {
            throw new HashMismatchException();
        }

        //if 3D Authentication is successful:
        if (in_array($userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'], [1, 2, 3, 4])) {
            $this->logger->debug('finishing payment', [
                'md_status' => $userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'],
            ]);
            $requestData  = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

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

            $contents     = $this->serializer->encode($requestData, $txType);
            $bankResponse = $this->send($contents, $txType, PosInterface::MODEL_3D_SECURE);
        } else {
            $this->logger->error('3d auth fail', [
                'md_status' => $userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'],
            ]);
        }

        end:
        $this->response = $this->responseDataMapper->map3DPaymentData($userVerifyResponse, $bankResponse);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $card = null): array
    {
        if (!$card instanceof CreditCardInterface) {
            throw new LogicException('Kredi kartı veya sipariş bilgileri eksik!');
        }

        $data = $this->getOosTransactionData($order, $txType, $card);

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $data['approved']) {
            $this->logger->error('enrollment fail response', $data);
            throw new Exception($data['respText']);
        }

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $this->get3DGatewayURL(), null, $data['oosRequestDataResponse']);
    }

    /** @return PosNetAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, string $paymentModel, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->debug('sending request', ['url' => $url]);

        if (!is_string($contents)) {
            throw new InvalidArgumentException(sprintf('Argument type must be XML string, %s provided.', gettype($contents)));
        }

        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => sprintf('xmldata=%s', $contents),
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }
}
