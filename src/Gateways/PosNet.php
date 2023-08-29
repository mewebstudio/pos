<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use LogicException;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PosNet
 */
class PosNet extends AbstractGateway
{
    /** @var string */
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
     *
     * @param object                                              $order
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     * @param AbstractCreditCard                                  $card
     *
     * @return array
     */
    public function getOosTransactionData(object $order, string $txType, AbstractCreditCard $card): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $txType, $card);
        $xml = $this->createXML($requestData);

        return $this->send($xml);
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $request = $request->request;
        $preparedOrder = $this->preparePaymentOrder($order);

        $this->logger->log(LogLevel::DEBUG, 'getting merchant request data');
        $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
            $this->account,
            $preparedOrder,
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
            $contents = $this->create3DPaymentXML($request->all(), $order, $txType);
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
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        if (!$card instanceof AbstractCreditCard) {
            throw new LogicException('Kredi kartı veya sipariş bilgileri eksik!');
        }
        $preparedOrder = $this->preparePaymentOrder($order);

        $data = $this->getOosTransactionData($preparedOrder, $txType, $card);

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $data['approved']) {
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception($data['respText']);
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $preparedOrder, $paymentModel, $txType, $this->get3DGatewayURL(), null, $data['oosRequestDataResponse']);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        if (is_string($contents)) {
            $response = $this->client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => sprintf('xmldata=%s', $contents),
            ]);
        } else {
            $response = $this->client->post($url, ['form_params' => $contents]);
        }

        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->XMLStringToArray($response->getBody()->getContents());
    }

    /** @return PosNetAccount */
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
     *
     * @param AbstractGateway::TX_* $txType kullanilmiyor
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
    public function createHistoryXML(array $customQueryData)
    {
        throw new NotImplementedException();
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
            'id'            => $order['id'],
            'payment_model' => $order['payment_model'] ?? self::MODEL_3D_SECURE,
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
        $orderTemp = [
            //id or ref_ret_num
            'id'          => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            //optional
            'auth_code'   => $order['auth_code'] ?? null,
        ];

        if (isset($orderTemp['id'])) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? self::MODEL_3D_SECURE;
        }

        return (object) $orderTemp;
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        $orderTemp = [
            //id or ref_ret_num
            'id'          => $order['id'] ?? null,
            'ref_ret_num' => $order['ref_ret_num'] ?? null,
            'amount'      => $order['amount'],
            'currency'    => $order['currency'] ?? 'TRY',
        ];

        if (isset($orderTemp['id'])) {
            $orderTemp['payment_model'] = $order['payment_model'] ?? self::MODEL_3D_SECURE;
        }

        return (object) $orderTemp;
    }
}
