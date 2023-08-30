<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use Mews\Pos\DataMapper\PayFlexCPV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexCPV4PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

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
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request): self
    {
        $resultCode = $request->query->get('Rc');
        if (null !== $resultCode && $this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $resultCode) {
            $this->logger->error('received error response from the bank', $request->query->all());
            $this->response = $this->responseDataMapper->map3DPayResponseData($request->query->all());

            return $this;
        }

        /** @var array{TransactionId: string, PaymentToken: string} $queryParams */
        $queryParams = $request->query->all();

        $statusRequestData = $this->requestDataMapper->create3DPaymentStatusRequestData($this->account, $queryParams);
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
         *     PaymentToken: string} $bankResponse */
        $bankResponse = $this->send($statusRequestData, null, $this->getQueryAPIUrl());

        $this->response = $this->responseDataMapper->map3DPayResponseData($bankResponse);

        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): self
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function history(array $meta)
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * {@inheritDoc}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $data */
        $data = $this->registerPayment($order, $txType, $card);

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
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = true): string
    {
        return parent::createXML(['VposRequest' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $url ?? $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        $isXML = is_string($contents);
        $body = $isXML ? ['body' => $contents] : ['form_params' => $contents];

        $response = $this->client->post($url, $body);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        $responseBody = $response->getBody()->getContents();

        try {
            $this->data = $this->XMLStringToArray($responseBody);
        } catch (NotEncodableValueException $notEncodableValueException) {
            if ($this->isHTML($responseBody)) {
                // if something wrong server responds with HTML content
                throw new Exception($responseBody, $notEncodableValueException->getCode(), $notEncodableValueException);
            }

            $this->data = json_decode($responseBody, true);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType): string
    {
        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $order, $txType, $card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null)
    {
        throw new NotImplementedException();
    }

    /**
     * TODO check if it is working
     * @inheritDoc
     */
    public function createStatusXML(array $order): array
    {
        return $this->requestDataMapper->createStatusRequestData($this->account, $order);
    }

    /**
     * TODO check if it is working
     * @inheritDoc
     */
    public function createHistoryXML(array $customQueryData): array
    {
        return $this->requestDataMapper->createHistoryRequestData($this->account, $customQueryData, $customQueryData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $order);

        return $this->createXML($requestData);
    }

    /**
     *
     * ORTAK ÖDEME SİSTEMİNE İŞLEM KAYDETME
     *
     * @param array<string, int|string|float|null>                $order
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     * @param AbstractCreditCard                                  $card
     *
     * Basarili durumda donen cevap formati: array{CommonPaymentUrl: string, PaymentToken: string, ErrorCode: null, ResponseMessage: null}
     * Basarisiz durumda donen cevap formati: array{CommonPaymentUrl: null, PaymentToken: null, ErrorCode: string, ResponseMessage: string}
     *
     * @return array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null}
     *
     * @throws Exception
     */
    public function registerPayment(array $order, string $txType, AbstractCreditCard $card = null): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $txType,
            $card
        );

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $response */
        $response = $this->send($requestData);

        return $response;
    }
}
