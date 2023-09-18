<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Exception;
use InvalidArgumentException;
use LogicException;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use function gettype;
use function sprintf;

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
     * Get OOS transaction data
     * siparis bilgileri ve kart bilgilerinin şifrelendiği adımdır.
     *
     * @param array<string, int|string|float|null>          $order
     * @param PosInterface::TX_PAY|PosInterface::TX_PRE_PAY $txType
     * @param AbstractCreditCard                            $card
     *
     * @return array{approved: string, respCode: string, respText: string, oosRequestDataResponse?: array{data1: string, data2: string, sign: string}}
     */
    public function getOosTransactionData(array $order, string $txType, AbstractCreditCard $card): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData($this->account, $order, $txType, $card);
        $xml         = $this->serializer->encode($requestData, $txType);

        return $this->send($xml, $txType);
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null): PosInterface
    {
        $request = $request->request;

        $this->logger->log(LogLevel::DEBUG, 'getting merchant request data');
        $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
            $this->account,
            $order,
            $request->all()
        );

        $contents           = $this->serializer->encode($requestData, $txType);
        $userVerifyResponse = $this->send($contents, $txType);
        $bankResponse       = null;

        if ($this->responseDataMapper::PROCEDURE_SUCCESS_CODE !== $userVerifyResponse['approved']) {
            goto end;
        }

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $userVerifyResponse['oosResolveMerchantDataResponse'])) {
            throw new HashMismatchException();
        }

        //if 3D Authentication is successful:
        if (in_array($userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'], [1, 2, 3, 4])) {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment', [
                'md_status' => $userVerifyResponse['oosResolveMerchantDataResponse']['mdStatus'],
            ]);
            $requestData  = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());
            $contents     = $this->serializer->encode($requestData, $txType);
            $bankResponse = $this->send($contents, $txType);
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
    public function make3DPayPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
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
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        if (!is_string($contents)) {
            throw new InvalidArgumentException(sprintf('Argument type must be XML string, %s provided.', gettype($contents)));
        }
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => sprintf('xmldata=%s', $contents),
        ]);

        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }

    /** @return PosNetAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }
}
