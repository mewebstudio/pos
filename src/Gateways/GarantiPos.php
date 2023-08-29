<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GarantiPos
 */
class GarantiPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'GarantiPay';

    /** @var GarantiPosAccount */
    protected $account;

    /** @var GarantiPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var GarantiPosResponseDataMapper */
    protected $responseDataMapper;

    /** @return GarantiPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = false): string
    {
        return parent::createXML(['GVPSRequest' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $request = $request->request;
        $bankResponse = null;
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            // todo mdstatus 7 oldugunda hash, hashparam deger gelmiyor, check3dhash calismiyor
            throw new HashMismatchException();
        }

        if (in_array($request->get('mdstatus'), [1, 2, 3, 4])) {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment', ['md_status' => $request->get('mdstatus')]);
            $contents     = $this->create3DPaymentXML($request->all(), $order, $txType);
            $bankResponse = $this->send($contents);
        } else {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('mdstatus')]);
        }


        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
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
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $preparedOrder, $paymentModel, $txType, $this->get3DGatewayURL(), $card);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        throw new NotImplementedException();
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
    public function createCancelXML(array $order): string
    {
        $preparedOrder = $this->prepareCancelOrder($order);

        $requestData = $this->requestDataMapper->createCancelRequestData($this->getAccount(), $preparedOrder);

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
    public function createHistoryXML(array $customQueryData): string
    {
        $preparedOrder = $this->prepareHistoryOrder($customQueryData);

        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $preparedOrder, $customQueryData);

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
    protected function preparePaymentOrder(array $order)
    {
        return (object) array_merge($order, [
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? 'TRY',
            'amount'      => $order['amount'],
            'ip'          => $order['ip'] ?? '',
            'email'       => $order['email'] ?? '',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id'          => $order['id'],
            'ref_ret_num' => $order['ref_ret_num'],
            'currency'    => $order['currency'] ?? 'TRY',
            'amount'      => $order['amount'],
            'ip'          => $order['ip'] ?? '',
            'email'       => $order['email'] ?? '',
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) [
            'id'          => $order['id'],
            'amount'      => 1, //sabit deger gonderilmesi gerekiyor
            'currency'    => $order['currency'] ?? 'TRY',
            'ip'          => $order['ip'] ?? '',
            'email'       => $order['email'] ?? '',
            'installment' => 0,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareHistoryOrder(array $order)
    {
        return $this->prepareStatusOrder($order);
    }

    /**
     * @inheritDoc
     */
    protected function prepareCancelOrder(array $order)
    {
        return (object) [
            'id'          => $order['id'],
            'amount'      => 1, //sabit deger gonderilmesi gerekiyor
            'currency'    => $order['currency'] ?? 'TRY',
            'ref_ret_num' => $order['ref_ret_num'],
            'ip'          => $order['ip'] ?? '',
            'email'       => $order['email'] ?? '',
            'installment' => 0,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        $refundOrder = $this->prepareCancelOrder($order);
        $refundOrder->amount = $order['amount'];

        return $refundOrder;
    }
}
