<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class VakifBankPos
 */
class VakifBankPos extends AbstractGateway
{
    /**
     * @const string
     */
    public const NAME = 'VakifPOS';

    /**
     * @var VakifBankAccount
     */
    protected $account;

    /** @var VakifBankPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var VakifBankPosResponseDataMapper */
    protected $responseDataMapper;

    /**
     * @return VakifBankAccount
     */
    public function getAccount()
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
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
        $contents = $this->create3DPaymentXML($request->all());
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
     * returns form data needed for 3d model
     *
     * @return array
     *
     * @throws Exception
     */
    public function get3DFormData(): array
    {
        if (!$this->card || !$this->order) {
            $this->logger->log(LogLevel::ERROR, 'tried to get 3D form data without setting order', [
                'order' => $this->order,
                'card_provided' => !!$this->card,
            ]);
            return [];
        }

        $data = $this->sendEnrollmentRequest();

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

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, '', null, $data['Message']['VERes']);
    }

    /**
     * Müşteriden kredi kartı bilgilerini aldıktan sonra GET 7/24 MPI’a kart “Kredi Kartı Kayıt Durumu”nun
     * (Enrollment Status) sorulması, yani kart 3-D Secure programına dâhil mi yoksa değil mi sorgusu
     *
     * @return array
     *
     * @throws Exception
     */
    public function sendEnrollmentRequest()
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, $this->card);

        return $this->send($requestData, $this->get3DGatewayURL());
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
    public function send($contents, ?string $url = null)
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
        } catch (NotEncodableValueException $e) {
            if ($this->isHTML($responseBody)) {
                // if something wrong server responds with HTML content
                throw new Exception($responseBody);
            }
            $this->data = json_decode($responseBody, true);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, $this->type, $this->card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * NOT: diger gatewaylerden farkli olarak vakifbank kredit bilgilerini bu asamada da istiyor.
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData, $this->card);

        return $this->createXML($requestData);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createStatusXML()
    {
        return $this->requestDataMapper->createStatusRequestData($this->account, $this->order);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        return $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        return (object) array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? 'TRY',
            'amount'      => $order['amount'],
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? 'TRY',
            'ip'       => $order['ip'],
        ];
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return (object) [
            'id' => $order['id'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object) $order;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) $order;
    }
}
