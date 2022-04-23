<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\DataMapper\VakifBankPosRequestDataMapper;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
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

    /**
     * @var AbstractCreditCard
     */
    protected $card;

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '0000' => 'approved',
        // TODO map other codes
    ];

    /** @var VakifBankPosRequestDataMapper */
    private $requestDataMapper;

    /**
     * @inheritDoc
     *
     * @param VakifBankAccount $account
     */
    public function __construct($config, $account, array $currencies)
    {
        $this->requestDataMapper              = new VakifBankPosRequestDataMapper($currencies);
        $this->types                          = $this->requestDataMapper->getTxTypeMappings();
        $this->currencies                     = $this->requestDataMapper->getCurrencyMappings();
        $this->cardTypeMapping                = $this->requestDataMapper->getCardTypeMapping();
        $this->recurringOrderFrequencyMapping = $this->requestDataMapper->getRecurringOrderFrequencyMapping();

        parent::__construct($config, $account, $currencies);
    }

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
        $gatewayResponse = $this->emptyStringsToNull($request->all());
        // 3D authorization failed
        if ('Y' !== $gatewayResponse['Status'] && 'A' !== $gatewayResponse['Status']) {
            $this->response = $this->map3DPaymentData($gatewayResponse, (object) []);

            return $this;
        }

        if ('A' === $gatewayResponse['Status']) {
            // TODO Half 3D Secure
            $this->response = $this->map3DPaymentData($gatewayResponse, (object) []);

            return $this;
        }

        $contents = $this->create3DPaymentXML($gatewayResponse);
        $bankResponse = $this->send($contents);

        $this->response = $this->map3DPaymentData($gatewayResponse, $bankResponse);

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
     * @throws Exception|GuzzleException
     */
    public function get3DFormData(): array
    {
        if (!$this->card || !$this->order) {
            return [];
        }

        $data = $this->sendEnrollmentRequest();
        $data = parent::emptyStringsToNull($data);

        return $this->requestDataMapper->create3DFormDataFromEnrollmentResponse($data);
    }

    /**
     * Müşteriden kredi kartı bilgilerini aldıktan sonra GET 7/24 MPI’a kart “Kredi Kartı Kayıt Durumu”nun
     * (Enrollment Status) sorulması, yani kart 3-D Secure programına dâhil mi yoksa değil mi sorgusu
     *
     * @return object
     *
     * @throws GuzzleException
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
        $client = new Client();
        $url = $url ?: $this->getApiURL();

        $isXML = is_string($contents);
        $body = $isXML ? ['form_params' => ['prmstr' => $contents]] : ['form_params' => $contents];

        $response = $client->request('POST', $url, $body);

        $responseBody = $response->getBody()->getContents();

        try {
            $this->data = $this->XMLStringToObject($responseBody);
        } catch (NotEncodableValueException $e) {
            if ($this->isHTML($responseBody)) {
                // if something wrong server responds with HTML content
                throw new Exception($responseBody);
            }
            $this->data = (object) json_decode($responseBody);
        }

        $this->data = $this->emptyStringsToNull($this->data);

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
        $this->requestDataMapper->createStatusRequestData($this->account, $this->order);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function createHistoryXML($customQueryData)
    {
        $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);
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
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $threeDAuthStatus = ('Y' === $raw3DAuthResponseData['Status']) ? 'approved' : 'declined';
        $paymentResponseData = [];

        if ('approved' === $threeDAuthStatus) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'id'            => null,
            'eci'           => $raw3DAuthResponseData['Eci'],
            'cavv'          => $raw3DAuthResponseData['Cavv'],
            'auth_code'     => null,
            'order_id'      => $raw3DAuthResponseData['VerifyEnrollmentRequestId'],
            'status'        => $threeDAuthStatus,
            'status_detail' => null,
            'error_code'    => 'declined' === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorCode'] : null,
            'error_message' => 'declined' === $threeDAuthStatus ? $raw3DAuthResponseData['ErrorMessage'] : null,
            'all'           => $rawPaymentResponseData,
            '3d_all'        => $raw3DAuthResponseData,
        ];

        if (empty($paymentResponseData)) {
            return (object) array_merge($this->getDefaultPaymentResponse(), $threeDResponse);
        }

        return (object) array_merge($threeDResponse, $paymentResponseData);
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        return $this->mapCancelResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        $status = 'declined';
        $resultCode = $rawResponseData->ResultCode;
        if ('0000' === $resultCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData->TransactionId ?? null,
            'auth_code'        => ('declined' !== $status) ? $rawResponseData->AuthCode : null,
            'host_ref_num'     => $rawResponseData->Rrn ?? null,
            'proc_return_code' => $resultCode,
            'trans_id'         => $rawResponseData->TransactionId ?? null,
            'error_code'       => ('declined' === $status) ? $rawResponseData->ResultDetail : null,
            'error_message'    => ('declined' === $status) ? $rawResponseData->ResultDetail : null,
            'status'           => $status,
            'status_detail'    => $rawResponseData->ResultDetail,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData): array
    {
        $commonResponse = $this->getCommonPaymentResponse($responseData);
        if ('approved' === $commonResponse['status']) {
            $commonResponse['id'] = $responseData->AuthCode;
            $commonResponse['trans_id'] = $responseData->TransactionId;
            $commonResponse['auth_code'] = $responseData->AuthCode;
            $commonResponse['host_ref_num'] = $responseData->Rrn;
            $commonResponse['order_id'] = $responseData->OrderId;
            $commonResponse['transaction_type'] = $responseData->TransactionType;
            $commonResponse['eci'] = $responseData->ECI;
        }

        return $commonResponse;
    }

    /**
     * TODO
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        return $rawResponseData;
    }

    /**
     * @inheritDoc
     */
    protected function preparePaymentOrder(array $order)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = (int) $order['installment'];
        }

        $currency = $order['currency'] ?? 'TRY';

        // Order
        return (object) array_merge($order, [
            'installment' => $installment,
            'currency'    => $this->requestDataMapper->mapCurrency($currency),
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
            'currency' => $this->requestDataMapper->mapCurrency($order['currency']),
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

    /**
     * @param $responseData
     *
     * @return array
     */
    private function getCommonPaymentResponse($responseData): array
    {
        $status = 'declined';
        $resultCode = $responseData->ResultCode;
        if ('0000' === $resultCode) {
            $status = 'approved';
        }

        return [
            'id'               => null,
            'trans_id'         => null,
            'auth_code'        => null,
            'host_ref_num'     => null,
            'order_id'         => null,
            'transaction'      => $this->type,
            'transaction_type' => null,
            'response'         => null,
            'eci'              => null,
            'proc_return_code' => $resultCode,
            'code'             => $resultCode,
            'status'           => $status,
            'status_detail'    => $responseData->ResultDetail,
            'error_code'       => ('declined' === $status) ? $resultCode : null,
            'error_message'    => ('declined' === $status) ? $responseData->ResultDetail : null,
            'all'              => $responseData,
        ];
    }

    /**
     * bankadan gelen response'da bos string degerler var.
     * bu metod ile bos string'leri null deger olarak degistiriyoruz
     *
     * @param string|object|array $data
     *
     * @return string|object|array
     */
    protected function emptyStringsToNull($data)
    {
        if (is_string($data)) {
            $data = '' === $data ? null : $data;
        } elseif (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = '' === $value ? null : $value;
            }
        } elseif (is_object($data)) {
            foreach ($data as $key => $value) {
                $data->{$key} = '' === $value ? null : $value;
            }
        }

        return $data;
    }
}
