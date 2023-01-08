<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Exceptions\HashMismatchException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * todo cardType verisi dokumantasyona gore kontrol edilmesi gerekiyor.
 * cardType gondermeden de su an calisiyor.
 *
 * @deprecated use Mews\Pos\Gateways\EstV3Pos.
 * For security reasons this class which uses sha1 hashing algorithm is not recommended to use.
 */
class EstPos extends AbstractGateway
{
    /**
     * @const string
     */
    public const NAME = 'EstPos';

    /**
     * @var EstPosAccount
     */
    protected $account;

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'ISO-8859-9', bool $ignorePiNode = false): string
    {
        return parent::createXML(['CC5Request' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $request = $request->request;
        $provisionResponse = null;
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        if ($request->get('mdStatus') !== '1') {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('mdStatus')]);
            /**
             * TODO hata durumu ele alinmasi gerekiyor
             * ornegin soyle bir hata donebilir
             * ["ProcReturnCode" => "99", "mdStatus" => "7", "mdErrorMsg" => "Isyeri kullanim tipi desteklenmiyor.",
             * "ErrMsg" => "Isyeri kullanim tipi desteklenmiyor.", "Response" => "Error", "ErrCode" => "3D-1007", ...]
             */
        } else {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment', ['md_status' => $request->get('mdStatus')]);
            $contents = $this->create3DPaymentXML($request->all());
            $provisionResponse = $this->send($contents);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $provisionResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }
        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->request->all())) {
            throw new HashMismatchException();
        }
        $this->response = $this->responseDataMapper->map3DHostResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(): array
    {
        if (!$this->order) {
            $this->logger->log(LogLevel::ERROR, 'tried to get 3D form data without setting order');
            return [];
        }
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $response = $this->client->post($url, ['body' => $contents]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);
        $this->data = $this->XMLStringToArray($response->getBody()->getContents());

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $bankResponse = $this->send($xml);

        $this->response = $this->responseDataMapper->mapHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @return EstPosAccount
     */
    public function getAccount()
    {
        return $this->account;
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
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $this->order);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);

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
    public function createRefundXML()
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $this->order);

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
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id' => $order['id'],
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
        return (object) $order;
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
        return (object) [
            'id'       => $order['id'],
            'currency' => $order['currency'] ?? 'TRY',
            'amount'   => $order['amount'],
        ];
    }
}
