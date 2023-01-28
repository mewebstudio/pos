<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deniz bankin desteklidigi Gateway
 * Class InterPos
 */
class InterPos extends AbstractGateway
{
    /**
     * @const string
     */
    public const NAME = 'InterPos';

    /** @var InterPosAccount */
    protected $account;

    /** @var InterPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var InterPosResponseDataMapper */
    protected $responseDataMapper;

    /**
     * @return InterPosAccount
     */
    public function getAccount(): InterPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $url = $url ?: $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $response = $this->client->post($url, ['form_params' => $contents]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        //genelde ;; delimiter kullanilmis, ama bazen arasinda ;;; boyle delimiter de var.
        $resultValues = preg_split('/(;;;|;;)/', $response->getBody()->getContents());
        $result       = [];
        foreach ($resultValues as $val) {
            [$key, $value] = explode('=', $val);
            $result[$key] = $value;
        }

        $this->data = $result;

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $bankResponse    = null;
        $request         = $request->request;
        $gatewayResponse = $request->all();

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }

        if ('1' !== $request->get('3DStatus')) {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('3DStatus')]);
            /**
             * TODO hata durumu ele alinmasi gerekiyor
             */
        } else {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment');
            $contents     = $this->create3DPaymentXML($gatewayResponse);
            $bankResponse = $this->send($contents);
        }


        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response  = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * Deniz bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $meta)
    {
        throw new NotImplementedException();
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
        $gatewayUrl = $this->get3DHostGatewayURL();
        if (self::MODEL_3D_SECURE === $this->account->getModel()) {
            $gatewayUrl = $this->get3DGatewayURL();
        } elseif (self::MODEL_3D_PAY === $this->account->getModel()) {
            $gatewayUrl = $this->get3DGatewayURL();
        }
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $gatewayUrl, $this->card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML()
    {
        return $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, $this->type, $this->card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        return $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        return $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);
    }

    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        return $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);
    }

    /**
     * @inheritDoc
     */
    public function createStatusXML()
    {
        return $this->requestDataMapper->createStatusRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        return $this->requestDataMapper->createCancelRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        return $this->requestDataMapper->createRefundRequestData($this->account, $this->order);
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
            'id'       => $order['id'],
            'amount'   => $order['amount'],
            'currency' => $order['currency'] ?? 'TRY',
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
        return (object) $order;
    }
}
