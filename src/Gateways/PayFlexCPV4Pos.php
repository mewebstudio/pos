<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Exception;
use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use function gettype;
use function is_string;
use function sprintf;

/**
 * PayFlex Common Payment (Ortak Ödeme) ISD v4.0
 * Dokumanlar: http://sanalpos.innova.com.tr/
 */
class PayFlexCPV4Pos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayFlex-Common-Payment-V4';

    /** @var PayFlexAccount */
    protected $account;

    /** @var PayFlexCPV4PosRequestDataMapper */
    protected $requestDataMapper;

    /** @var PayFlexCPV4PosResponseDataMapper */
    protected $responseDataMapper;

    /** @return PayFlexAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * todo implement
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request): PosInterface
    {
        $resultCode = $request->query->get('Rc');
        if (null !== $resultCode && $this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $resultCode) {
            $this->logger->error('received error response from the bank', $request->query->all());
            $this->response = $this->responseDataMapper->map3DPayResponseData($request->query->all());

            return $this;
        }

        /** @var array{TransactionId: string, PaymentToken: string} $queryParams */
        $queryParams = $request->query->all();

        // Burda odemenin basarili olup olmadigini sorguluyoruz.
        $requestData = $this->requestDataMapper->create3DPaymentStatusRequestData($this->account, $queryParams);

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), PosInterface::TX_PAY);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->log(LogLevel::DEBUG, 'Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /**
         * sending request to make sure that payment was successful
         * @var array{ErrorCode: string}|array{
         *     Rc: string,
         *     AuthCode: string,
         *     TransactionId: string,
         *     PaymentToken: string,
         *     MaskedPan: string}|array{
         *     Rc: string,
         *     Message: string,
         *     TransactionId: string,
         *     PaymentToken: string} $bankResponse
         */
        $bankResponse = $this->send($requestData, PosInterface::TX_PAY, $this->getQueryAPIUrl());

        $this->response = $this->responseDataMapper->map3DPayResponseData($bankResponse);

        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * {@inheritDoc}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $data */
        $data = $this->registerPayment($order, $txType, $paymentModel, $card);

        if (null !== $data['ErrorCode']) {
            $this->logger->log(LogLevel::ERROR, 'payment register fail response', $data);
            throw new Exception('İşlem gerçekleştirilemiyor');
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            null,
            [],
            null,
            null,
            null,
            null,
            $data
        );
    }

    /**
     *
     * ORTAK ÖDEME SİSTEMİNE İŞLEM KAYDETME
     *
     * @phpstan-param PosInterface::TX_PAY|PosInterface::TX_PRE_PAY $txType
     * @phpstan-param PosInterface::MODEL_3D_*                      $paymentModel
     *
     * @param array<string, int|string|float|null> $order
     * @param string                               $txType
     * @param string                               $paymentModel
     * @param AbstractCreditCard|null              $card
     *
     * Basarili durumda donen cevap formati: array{CommonPaymentUrl: string, PaymentToken: string, ErrorCode: null,
     * ResponseMessage: null} Basarisiz durumda donen cevap formati: array{CommonPaymentUrl: null, PaymentToken: null,
     * ErrorCode: string, ResponseMessage: string}
     *
     * @return array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null}
     *
     * @throws Exception
     */
    public function registerPayment(array $order, string $txType, string $paymentModel, AbstractCreditCard $card = null): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $txType,
            $paymentModel,
            $card
        );

        $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
        $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->log(LogLevel::DEBUG, 'Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $response */
        $response = $this->send($requestData, $txType);

        return $response;
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, ?string $url = null): array
    {
        $url = $url ?? $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        if (!is_string($contents)) {
            throw new InvalidArgumentException(sprintf('Argument type must be XML string, %s provided.', gettype($contents)));
        }

        $response = $this->client->post($url, ['body' => $contents]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }
}
