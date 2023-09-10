<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use LogicException;
use Mews\Pos\DataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * PayFlex MPI ISD v4 gateway'i destekler (INNOVA BİLİŞİM ÇÖZÜMLERİ A.Ş)
 * Dokumanlar: http://sanalpos.innova.com.tr/
 */
class PayFlexV4Pos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayFlexV4';

    /** @var PayFlexAccount */
    protected $account;

    /** @var PayFlexV4PosRequestDataMapper */
    protected $requestDataMapper;

    /** @var PayFlexV4PosResponseDataMapper */
    protected $responseDataMapper;

    /** @return PayFlexAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $request = $request->request;
        $status = $request->get('Status');
        // 3D authorization failed
        if ('Y' !== $status && 'A' !== $status) {
            $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), []);

            return $this;
        }

        if ('A' === $status) {
            // TODO Half 3D Secure
            $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), []);

            return $this;
        }

        $this->logger->log(LogLevel::DEBUG, 'finishing payment', ['md_status' => $status]);
        /** @var array{Eci: string, Cavv: string, VerifyEnrollmentRequestId: string} $requestData */
        $requestData = $request->all();
        $contents = $this->create3DPaymentXML($requestData, $order, $txType, $card);
        $bankResponse = $this->send($contents);

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * TODO
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
        if (!$card instanceof AbstractCreditCard) {
            throw new LogicException('Kredi kartı veya sipariş bilgileri eksik!');
        }

        $data = $this->sendEnrollmentRequest($order, $card);

        $status = $data['Message']['VERes']['Status'];
        /**
         * Status values:
         * Y:Kart 3-D Secure programına dâhil
         * N:Kart 3-D Secure programına dâhil değil
         * U:İşlem gerçekleştirilemiyor
         * E:Hata durumu
         */
        if ('E' === $status) {
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception($data['ErrorMessage'], $data['MessageErrorCode']);
        }

        if ('N' === $status) {
            //half secure olarak devam et yada satisi iptal et.
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception('Kart 3-D Secure programına dâhil değil');
        }

        if ('U' === $status) {
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception('İşlem gerçekleştirilemiyor');
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, null, $paymentModel, $txType, '', null, $data['Message']['VERes']);
    }

    /**
     * Müşteriden kredi kartı bilgilerini aldıktan sonra GET 7/24 MPI’a kart “Kredi Kartı Kayıt Durumu”nun
     * (Enrollment Status) sorulması, yani kart 3-D Secure programına dâhil mi yoksa değil mi sorgusu
     *
     * @param array<string, int|string|float|null> $order
     * @param AbstractCreditCard                   $card
     *
     * @return array
     *
     * @throws Exception
     */
    public function sendEnrollmentRequest(array $order, AbstractCreditCard $card): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $card);

        return $this->send($requestData, null, $this->get3DGatewayURL());
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
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $url ?: $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        $isXML = is_string($contents);
        $body = $isXML ? ['form_params' => ['prmstr' => $contents]] : ['form_params' => $contents];

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
     * NOT: diger gatewaylerden farkli olarak payflex kredit bilgilerini bu asamada da istiyor.
     * @inheritDoc
     *
     * @param array{Eci: string, Cavv: string, VerifyEnrollmentRequestId: string} $responseData
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): string
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData, $card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

        return parent::createXML(['SearchRequest' => $requestData]);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData): array
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
}
