<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

/**
 * Class PayForPos
 */
class PayForPos extends AbstractGateway
{
    /**
     * @const string
     */
    public const NAME = 'PayForPOS';

    /**
     * @var PayForAccount
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
        '00'   => 'approved',
        '96'   => 'general_error',
        'V004' => 'invalid_credentials',
        'V001' => 'invalid_credentials',
        'V111' => 'general_error',
        'V013' => 'reject',
        'V014' => 'request_rejected',
        'V015' => 'request_rejected',
        'V025' => 'general_error',
        'V029' => 'general_error',
        'V034' => 'try_again',
        'V036' => 'general_error',
        'M025' => 'general_error',
        'M042' => 'general_error',
        'M002' => 'invalid_transaction',
        'M012' => 'invalid_transaction',
        'MR15' => 'try_again',
        'M041' => 'reject',
        'M049' => 'invalid_credentials',
    ];

    /**
     * @var PayForPosRequestDataMapper
     */
    protected $requestDataMapper;

    /**
     * @param PayForAccount $account
     * @param PayForPosRequestDataMapper $requestDataMapper
     */
    public function __construct(
        array $config,
        AbstractPosAccount $account,
        AbstractRequestDataMapper $requestDataMapper,
        HttpClient $client,
        LoggerInterface $logger
    ) {
        parent::__construct($config, $account, $requestDataMapper, $client, $logger);
    }

    /**
     * @return PayForAccount
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
        $bankResponse = null;
        //if customer 3d verification passed finish payment
        if ($this->check3DHash($this->account, $request->all()) && '1' === $request->get('3DStatus')) {
            //valid ProcReturnCode is V033 in case of success 3D Authentication
            $contents = $this->create3DPaymentXML($request->all());
            $bankResponse = $this->send($contents);
        } else {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('3DStatus')]);
        }

        $this->response = $this->map3DPaymentData($request->all(), $bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response = $this->map3DPayResponseData($request->request->all());

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
     * Refund Order
     * refund amount should be exactly the same with order amount.
     * otherwise operation will be rejected
     *
     * Warning: You can not use refund for purchases made at the same date.
     * Instead, you need to use cancel.
     *
     * @inheritDoc
     */
    public function refund()
    {
        return parent::refund();
    }

    /**
     * Fetches All Transaction/Action/Order history, both failed and successful, for the given date ReqDate
     * or transactions related to the queried order if orderId is given
     * Note: history request to gateway returns JSON response
     * If both reqDate and orderId provided then finansbank will take into account only orderId
     *
     * returns list array or items for the given date,
     * if orderId specified in request then return array of transactions (refund|pre|post|cancel)
     * both successful and failed, for the related orderId
     * @inheritDoc
     */
    public function history(array $meta)
    {
        return parent::history($meta);
    }


    /**
     * returns form data needed for 3d, 3d_pay and 3d_host models
     *
     * @return array
     */
    public function get3DFormData(): array
    {
        if (!$this->order) {
            $this->logger->log(LogLevel::ERROR, 'tried to get 3D form data without setting order');
            return [];
        }
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        $gatewayURL = $this->get3DGatewayURL();
        if (self::MODEL_3D_HOST === $this->account->getModel()) {
            $gatewayURL = $this->get3DHostGatewayURL();
        }

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $gatewayURL, $this->card);
    }


    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'body'    => $contents,
        ]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        $response = $response->getBody()->getContents();

        /**
         * Finansbank XML Response some times are in following format:
         * <MbrId>5</MbrId>\r\n
         * <MD>\r\n
         * </MD>\r\n
         * <Hash>\r\n
         * </Hash>\r\n
         * redundant whitespaces causes non-empty value for response properties
         */
        $response = preg_replace('/\\r\\n\s*/', '', $response);

        try {
            $this->data = $this->XMLStringToObject($response);
        } catch (NotEncodableValueException $e) {
            //Finansbank's history request response is in JSON format
            $this->data = (object) json_decode($response);
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = false): string
    {
        return parent::createXML(['PayforRequest' => $nodes], $encoding, $ignorePiNode);
    }

    /**
     * validates response hash
     *
     * @param AbstractPosAccount $account
     * @param array              $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $hashData = [
            $account->getClientId(),
            $account->getStoreKey(),
            $data['OrderId'],
            $data['AuthCode'],
            $data['ProcReturnCode'],
            $data['3DStatus'],
            $data['ResponseRnd'],
            $account->getUsername(),
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        $hash = $this->hashString($hashStr);

        if ($hash === $data['ResponseHash']) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }

        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data' => $data,
            'generated_hash' => $hash,
            'expected_hash' => $data['ResponseHash']
        ]);

        return false;
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
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, '', $responseData);

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
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response' => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $threeDAuthStatus = ('1' === $raw3DAuthResponseData['3DStatus']) ? 'approved' : 'declined';
        $paymentResponseData = [];

        if ('approved' === $threeDAuthStatus) {
            $paymentResponseData = $this->mapPaymentResponse($rawPaymentResponseData);
        }

        $threeDResponse = [
            'id'               => $raw3DAuthResponseData['AuthCode'],
            'trans_id'         => $raw3DAuthResponseData['OrderId'],
            'auth_code'        => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num'     => $raw3DAuthResponseData['HostRefNum'],
            'order_id'         => $raw3DAuthResponseData['OrderId'],
            'proc_return_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'code'             => $raw3DAuthResponseData['ProcReturnCode'],
            'status'           => 'declined',
            'status_detail'    => $this->codes[$raw3DAuthResponseData['ProcReturnCode']] ?? null,
            'error_code'       => $raw3DAuthResponseData['ProcReturnCode'],
            'error_message'    => $raw3DAuthResponseData['ErrMsg'],
        ];

        if (empty($paymentResponseData)) {
            return (object) array_merge($this->getDefaultPaymentResponse(), $threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData));
        }

        return (object) array_merge($threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData), $paymentResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $status = '00' === $raw3DAuthResponseData['ProcReturnCode'] ? 'approved' : 'declined';

        $threeDResponse = [
            'id'               => $raw3DAuthResponseData['AuthCode'],
            'trans_id'         => $raw3DAuthResponseData['OrderId'],
            'auth_code'        => $raw3DAuthResponseData['AuthCode'],
            'host_ref_num'     => $raw3DAuthResponseData['HostRefNum'],
            'order_id'         => $raw3DAuthResponseData['OrderId'],
            'proc_return_code' => $raw3DAuthResponseData['ProcReturnCode'],
            'code'             => $raw3DAuthResponseData['ProcReturnCode'],
            'status'           => $status,
            'status_detail'    => $this->codes[$raw3DAuthResponseData['ProcReturnCode']] ?? null,
            'error_code'       => ('approved' !== $status) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'error_message'    => ('approved' !== $status) ? $raw3DAuthResponseData['ErrMsg'] : null,
            'transaction'      => array_search($raw3DAuthResponseData['TxnType'], $this->requestDataMapper->getTxTypeMappings(), true),
            'transaction_type' => $this->type,
        ];

        return (object) array_merge($threeDResponse, $this->map3DCommonResponseData($raw3DAuthResponseData));
    }

    /**
     * returns mapped data of the common response data among all 3d models.
     *
     * @param $raw3DAuthResponseData
     *
     * @return array
     */
    protected function map3DCommonResponseData($raw3DAuthResponseData): array
    {
        $threeDAuthStatus = ('1' === $raw3DAuthResponseData['3DStatus']) ? 'approved' : 'declined';

        return [
            'transaction_security' => $raw3DAuthResponseData['SecureType'],
            'hash'                 => $raw3DAuthResponseData['ResponseHash'],
            'rand'                 => $raw3DAuthResponseData['ResponseRnd'],
            'masked_number'        => $raw3DAuthResponseData['CardMask'],
            'amount'               => $raw3DAuthResponseData['PurchAmount'],
            'currency'             => array_search($raw3DAuthResponseData['Currency'], $this->requestDataMapper->getCurrencyMappings()),
            'tx_status'            => $raw3DAuthResponseData['TxnResult'],
            'xid'                  => $raw3DAuthResponseData['PayerTxnId'],
            'md_code'              => $raw3DAuthResponseData['ProcReturnCode'],
            'md_status'            => $raw3DAuthResponseData['3DStatus'],
            'md_error_code'        => ('declined' === $threeDAuthStatus) ? $raw3DAuthResponseData['ProcReturnCode'] : null,
            'md_error_message'     => ('declined' === $threeDAuthStatus) ? $raw3DAuthResponseData['ErrMsg'] : null,
            'md_status_detail'     => $this->codes[$raw3DAuthResponseData['ProcReturnCode']] ?? null,
            'eci'                  => $raw3DAuthResponseData['Eci'],
            '3d_all'               => $raw3DAuthResponseData,
        ];
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
        if ('00' === $rawResponseData->ProcReturnCode) {
            $status = 'approved';
        }

        return (object) [
            'order_id'         => $rawResponseData->TransId ?? null,
            'auth_code'        => ('declined' !== $status) ? $rawResponseData->AuthCode : null,
            'host_ref_num'     => $rawResponseData->HostRefNum ?? null,
            'proc_return_code' => $rawResponseData->ProcReturnCode ?? null,
            'trans_id'         => $rawResponseData->TransId ?? null,
            'error_code'       => ('declined' === $status) ? $rawResponseData->ProcReturnCode : null,
            'error_message'    => ('declined' === $status) ? $rawResponseData->ErrMsg : null,
            'status'           => $status,
            'status_detail'    => $this->codes[$rawResponseData->ProcReturnCode] ?? null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$responseData]);

        $status = 'declined';
        if ('00' === $responseData->ProcReturnCode) {
            $status = 'approved';
        }

        $mappedResponse = [
            'id'               => $responseData->AuthCode,
            'order_id'         => $responseData->TransId,
            'trans_id'         => $responseData->TransId,
            'transaction_type' => $this->type,
            'transaction'      => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'auth_code'        => $responseData->AuthCode,
            'host_ref_num'     => $responseData->HostRefNum,
            'proc_return_code' => $responseData->ProcReturnCode,
            'code'             => $responseData->ProcReturnCode,
            'status'           => $status,
            'status_detail'    => $this->codes[$responseData->ProcReturnCode] ?? null,
            'error_code'       => ('declined' === $status) ? $responseData->ProcReturnCode : null,
            'error_message'    => ('declined' === $status) ? $responseData->ErrMsg : null,
            'all'              => $responseData,
        ];

        $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        //status of the request
        $status = 'declined';
        if ('00' === $rawResponseData->ProcReturnCode) {
            $status = 'approved';
        }

        //status of the requested order
        $orderStatus = null;
        if ('approved' === $status && empty($rawResponseData->AuthCode)) {
            $orderStatus = 'declined';
        } elseif ('approved' === $status && !empty($rawResponseData->AuthCode)) {
            $orderStatus = 'approved';
        }

        return (object) [
            'auth_code'        => $rawResponseData->AuthCode ?? null,
            'order_id'         => $rawResponseData->OrderId ?? null,
            'org_order_id'     => $rawResponseData->OrgOrderId ?? null,
            'proc_return_code' => $rawResponseData->ProcReturnCode ?? null,
            'error_message'    => ('declined' === $status) ? $rawResponseData->ErrMsg : null,
            'host_ref_num'     => $rawResponseData->HostRefNum ?? null,
            'order_status'     => $orderStatus,
            'process_type'     => isset($rawResponseData->TxnType) ? array_search($rawResponseData->TxnType, $this->requestDataMapper->getTxTypeMappings(), true) : null,
            'masked_number'    => $rawResponseData->CardMask ?? null,
            'amount'           => $rawResponseData->PurchAmount ?? null,
            'currency'         => isset($rawResponseData->Currency) ? array_search($rawResponseData->Currency, $this->requestDataMapper->getCurrencyMappings()) : null,
            'status'           => $status,
            'status_detail'    => $this->codes[$rawResponseData->ProcReturnCode] ?? null,
            'all'              => $rawResponseData,
        ];
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
        return (object) [
            //reqDate or order id
            'reqDate' => $order['reqDate'] ?? null,
            'id'      => $order['id'] ?? null,
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
}
