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
     * @param array<string, int|string|float|null>                $order
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     * @param AbstractCreditCard                                  $card
     *
     * @return array
     */
    public function getOosTransactionData(array $order, string $txType, AbstractCreditCard $card): array
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

        $this->logger->log(LogLevel::DEBUG, 'getting merchant request data');
        $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
            $this->account,
            $order,
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

        $data = $this->getOosTransactionData($order, $txType, $card);

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $data['approved']) {
            $this->logger->log(LogLevel::ERROR, 'enrollment fail response', $data);
            throw new Exception($data['respText']);
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $this->get3DGatewayURL(), null, $data['oosRequestDataResponse']);
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
     * @inheritDoc
     *
     * @param AbstractGateway::TX_* $txType kullanilmiyor
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): string
    {
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $responseData);

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
        $requestData = $this->requestDataMapper->createStatusRequestData($this->account, $order);

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
     * @inheritDoc
     */
    public function createRefundXML(array $order): string
    {
        $requestData = $this->requestDataMapper->createRefundRequestData($this->account, $order);

        return $this->createXML($requestData);
    }
}
