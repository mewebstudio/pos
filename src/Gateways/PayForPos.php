<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\PayForPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
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
     * @inheritDoc
     *
     * @param PayForAccount $account
     */
    public function __construct(array $config, AbstractPosAccount $account, AbstractRequestDataMapper $requestDataMapper)
    {
        parent::__construct($config, $account, $requestDataMapper);
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
        $bankResponse = null;
        //if customer 3d verification passed finish payment
        if ($this->check3DHash($this->account, $request->request->all()) && '1' === $request->get('3DStatus')) {
            //valid ProcReturnCode is V033 in case of success 3D Authentication
            $contents = $this->create3DPaymentXML($request->request->all());
            $bankResponse = $this->send($contents);
        }

        $this->response = $this->map3DPaymentData($request->request->all(), $bankResponse);

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
            return [];
        }

        $gatewayURL = $this->get3DGatewayURL();
        if (self::MODEL_3D_HOST === $this->account->getModel()) {
            $gatewayURL = $this->get3DHostGatewayURL();
        }

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $gatewayURL, $this->card);
    }


    /**
     * @inheritDoc
     */
    public function send($postData, ?string $url = null)
    {
        $client = new Client();

        $response = $client->request('POST', $this->getApiURL(), [
            'headers' => [
                'Content-Type' => 'text/xml; charset=UTF-8',
            ],
            'body'    => $postData,
        ]);

        $contents = $response->getBody()->getContents();

        /**
         * Finansbank XML Response some times are in following format:
         * <MbrId>5</MbrId>\r\n
         * <MD>\r\n
         * </MD>\r\n
         * <Hash>\r\n
         * </Hash>\r\n
         * redundant whitespaces causes non-empty value for response properties
         */
        $contents = preg_replace('/\\r\\n\s*/', '', $contents);

        try {
            $this->data = $this->XMLStringToObject($contents);
        } catch (NotEncodableValueException $e) {
            //Finansbank's history request response is in JSON format
            $this->data = (object) json_decode($contents);
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

        return $hash === $data['ResponseHash'];
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
            'transaction_type' => array_search($raw3DAuthResponseData['TxnType'], $this->types, true),
            'transaction'      => $this->type,
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
        $status = 'declined';
        if ('00' === $responseData->ProcReturnCode) {
            $status = 'approved';
        }

        return [
            'id'               => $responseData->AuthCode,
            'order_id'         => $responseData->TransId,
            'trans_id'         => $responseData->TransId,
            'transaction_type' => $this->type,
            'transaction'      => $this->type,
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
            'process_type'     => isset($rawResponseData->TxnType) ? array_search($rawResponseData->TxnType, $this->types, true) : null,
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
        // Installment
        $installment = 0;
        if (isset($order['installment']) && $order['installment'] > 1) {
            $installment = (int) $order['installment'];
        }

        $currency = $order['currency'] ?? 'TRY';

        // Order
        return (object) array_merge($order, [
            'installment' => $installment,
            'currency'    => $currency,
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
            'currency' => $order['currency'],
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
