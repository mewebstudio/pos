<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PosNet
 */
class PosNet extends AbstractGateway
{
    public const NAME = 'PosNet';

    /** @var PosNetAccount */
    protected $account;

    /** @var PosNetRequestDataMapper */
    protected $requestDataMapper;


    /** @var PosNetResponseDataMapper */
    protected $responseDataMapper;

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'ISO-8859-9', bool $ignorePiNode = false): string
    {
        return parent::createXML(['posnetRequest' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        throw new NotImplementedException();
    }

    /**
     * Get OOS transaction data
     * siparis bilgileri ve kart bilgilerinin şifrelendiği adımdır.
     * @return array
     */
    public function getOosTransactionData()
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $this->order, $this->type, $this->card);
        $xml = $this->createXML($requestData);

        return $this->send($xml);
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $request = $request->request;

        $this->logger->log(LogLevel::DEBUG, 'getting merchant request data');
        $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
            $this->account,
            $this->order,
            $request->all()
        );

        $contents = $this->createXML($requestData);
        $userVerifyResponse = $this->send($contents);
        $bankResponse = null;

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $userVerifyResponse['approved']) {
            goto end;
        }

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $userVerifyResponse['oosResolveMerchantDataResponse'])) {
            throw new HashMismatchException();
        }

        //if 3D Authentication is successful:
        if (in_array($userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'], [1, 2, 3, 4])) {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment', [
                'md_status' =>$userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'],
            ]);
            $contents = $this->create3DPaymentXML($request->all());
            $bankResponse = $this->send($contents);
        } else {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', [
                'md_status' => $userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'],
            ]);
        }
        end:
        $this->response = $this->responseDataMapper->map3DPaymentData($userVerifyResponse, $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
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

        $data = $this->getOosTransactionData();

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $data['approved']) {
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception($data['respText']);
        }
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card, $data['oosRequestDataResponse']);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        /** @phpstan-ignore-next-line */
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => "xmldata=$contents",
        ]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        $this->data = $this->XMLStringToArray($response->getBody()->getContents());

        return $this->data;
    }

    /**
     * @return PosNetAccount
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
        // her hangi bir txType yeterli
        $txType = AbstractGateway::TX_PAY;
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $txType, $responseData);

        return $this->createXML($requestData);
    }


    /**
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        throw new NotImplementedException();
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
            'id'          => $order['id'],
            'installment' => $order['installment'] ?? 0,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? 'TRY',
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function preparePostPaymentOrder(array $order)
    {
        return (object) [
            'id'           => $order['id'],
            'amount'       => $order['amount'],
            'installment'  => $order['installment'] ?? 0,
            'currency'     => $order['currency'] ?? 'TRY',
            'ref_ret_num' => $order['ref_ret_num'],
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareStatusOrder(array $order)
    {
        return (object) [
            'id' => $order['id'],
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
            //id or ref_ret_num
            'id'           => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            //optional
            'auth_code'    => $order['auth_code'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) [
            //id or ref_ret_num
            'id'           => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            'amount'       => $order['amount'],
            'currency'     => $order['currency'] ?? 'TRY',
        ];
    }
}
