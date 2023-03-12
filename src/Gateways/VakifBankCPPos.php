<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use LogicException;
use Mews\Pos\DataMapper\ResponseDataMapper\VakifBankCPPosResponseDataMapper;
use Mews\Pos\DataMapper\VakifBankCPPosRequestDataMapper;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * ORTAK ÖDEME (Common Payment) API
 */
class VakifBankCPPos extends AbstractGateway
{
    public const NAME = 'Vakif-Common-Payment';

    /** @var VakifBankAccount */
    protected $account;

    /** @var VakifBankCPPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var VakifBankCPPosResponseDataMapper */
    protected $responseDataMapper;

    /** @return VakifBankAccount */
    public function getAccount(): VakifBankAccount
    {
        return $this->account;
    }

    /**
     * todo implement
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
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
        $bankResponse = $this->send($statusRequestData, $this->getQueryAPIUrl());

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
    public function get3DFormData(): array
    {
        if (null === $this->order) {
            $this->logger->log(LogLevel::ERROR, 'tried to get 3D form data without setting order', [
                'order' => $this->order,
                'card_provided' => (bool) $this->card,
            ]);

            throw new LogicException('Sipariş bilgileri eksik!');
        }

        /** @var array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null} $data */
        $data = $this->registerPayment();

        if (null !== $data['ErrorCode']) {
            $this->logger->log(LogLevel::ERROR, 'payment register fail response', $data);
            throw new Exception('İşlem gerçekleştirilemiyor');
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            null,
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
    public function send($contents, ?string $url = null)
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
        } catch (NotEncodableValueException $e) {
            if ($this->isHTML($responseBody)) {
                // if something wrong server responds with HTML content
                throw new Exception($responseBody, $e->getCode(), $e);
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
        if (null === $this->order) {
            throw new LogicException('sipariş bilgileri eksik!');
        }

        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        throw new NotImplementedException();
    }

    /**
     * TODO check if it is working
     * @inheritDoc
     */
    public function createStatusXML()
    {
        return $this->requestDataMapper->createStatusRequestData($this->account, $this->order);
    }

    /**
     * TODO check if it is working
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
        if (null === $this->order) {
            $this->logger->log(LogLevel::ERROR, 'cancel data create without order data', [
                'order' => $this->order,
            ]);

            throw new LogicException('Sipariş bilgileri eksik!');
        }
        
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
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) $order;
    }

    /**
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


    /**
     * @return string
     */
    public function getQueryAPIUrl(): string
    {
        return $this->config['urls']['query'][$this->getModeInWord()];
    }


    /**
     * ORTAK ÖDEME SİSTEMİNE İŞLEM KAYDETME
     * Basarili durumda donen cevap formati: array{CommonPaymentUrl: string, PaymentToken: string, ErrorCode: null, ResponseMessage: null}
     * Basarisiz durumda donen cevap formati: array{CommonPaymentUrl: null, PaymentToken: null, ErrorCode: string, ResponseMessage: string}
     * @return array{CommonPaymentUrl: string|null, PaymentToken: string|null, ErrorCode: string|null, ResponseMessage: string|null}
     *
     * @throws Exception
     */
    public function registerPayment(): array
    {
        if (null === $this->order) {
            $this->logger->log(LogLevel::ERROR, 'register payment without setting order', [
                'order' => $this->order,
                'card_provided' => (bool) $this->card,
            ]);

            throw new LogicException('Sipariş bilgileri eksik!');
        }

        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $this->order,
            $this->type,
            $this->card
        );

        return $this->send($requestData);
    }
}
