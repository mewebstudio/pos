<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
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
    /** @var string */
    public const NAME = 'EstPos';

    /** @var EstPosAccount */
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
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
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
            $contents = $this->create3DPaymentXML($request->all(), $order, $txType);
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
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $preparedOrder, $paymentModel, $txType, $this->get3DGatewayURL(), $card);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
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

    /** @return EstPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType): string
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        $requestData = $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $preparedOrder, $txType, $card);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML(array $order): string
    {
        $preparedOrder = $this->preparePostPaymentOrder($order);

        $requestData = $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $preparedOrder);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): string
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $preparedOrder, $txType, $responseData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML(array $order): string
    {
        $preparedOrder = $this->prepareStatusOrder($order);

        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $preparedOrder);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData): string
    {
        $preparedOrder = $this->prepareHistoryOrder($customQueryData);

        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $preparedOrder, $customQueryData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML(array $order): string
    {
        $preparedOrder = $this->prepareCancelOrder($order);

        $requestData = $this->requestDataMapper->createCancelRequestData($this->account, $preparedOrder);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML(array $order): string
    {
        $preparedOrder = $this->prepareRefundOrder($order);

        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $preparedOrder);

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
