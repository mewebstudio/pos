<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\Client\HttpClient;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GarantiPos
 */
class GarantiPos extends AbstractGateway
{
    /**
     * @const string
     */
    public const NAME = 'GarantiPay';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '00' => 'approved',
        '01' => 'bank_call',
        '02' => 'bank_call',
        '05' => 'reject',
        '09' => 'try_again',
        '12' => 'invalid_transaction',
        '28' => 'reject',
        '51' => 'insufficient_balance',
        '54' => 'expired_card',
        '57' => 'does_not_allow_card_holder',
        '62' => 'restricted_card',
        '77' => 'request_rejected',
        '99' => 'general_error',
    ];

    /** @var GarantiPosAccount */
    protected $account;

    /**4 @var AbstractCreditCard */
    protected $card;

    /** @var GarantiPosRequestDataMapper */
    protected $requestDataMapper;

    /**
     * @param GarantiPosAccount $account
     * @param GarantiPosRequestDataMapper $requestDataMapper
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
     * @return GarantiPosAccount
     */
    public function getAccount()
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
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash(array $data): bool
    {
        $hashParams = $data['hashparams'];
        $hashParamsVal = $data['hashparamsval'];
        $hashParam = $data['hash'];
        $paramsVal = '';

        $hashParamsArr = explode(':', $hashParams);
        foreach ($hashParamsArr as $value) {
            if (!empty($value) && isset($data[$value])) {
                $paramsVal = $paramsVal.$data[$value];
            }
        }

        $hashVal = $paramsVal.$this->account->getStoreKey();
        $hash = $this->hashString($hashVal);

        //todo simplify this if check
        if ($hashParams && !($paramsVal !== $hashParamsVal || $hashParam !== $hash)) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }
        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data' => $data,
            'generated_hash' => $hash,
            'expected_hash' => $hashParam
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request)
    {
        $request = $request->request;
        $bankResponse = null;
        if ($this->check3DHash($request->all())) {
            if (in_array($request->get('mdstatus'), [1, 2, 3, 4])) {
                $this->logger->log(LogLevel::DEBUG, 'finishing payment', ['md_status' => $request->get('mdstatus')]);
                $contents     = $this->create3DPaymentXML($request->all());
                $bankResponse = $this->send($contents);
            } else {
                $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $request->get('mdstatus')]);
            }
        }

        $this->response = (object) $this->map3DPaymentData($request->all(), $bankResponse);
        $this->logger->log(LogLevel::DEBUG, 'finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request)
    {
        $this->response = (object) $this->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta)
    {
        $xml = $this->createHistoryXML($meta);

        $bankResponse = $this->send($xml);

        $this->response = $this->mapHistoryResponse($bankResponse);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);
        $response = $this->client->post($url, ['body' => $contents]);
        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);
        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

        return $this->data;
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
        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card);
    }

    /**
     * TODO
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        throw new NotImplementedException();
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
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);

        return $this->createXML($requestData);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        $requestData = $this->requestDataMapper->createCancelRequestData($this->getAccount(), $this->getOrder());

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
    public function createHistoryXML($customQueryData)
    {
        $requestData = $this->requestDataMapper->createHistoryRequestData($this->account, $this->order, $customQueryData);

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
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping 3D payment data', [
            '3d_auth_response' => $raw3DAuthResponseData,
            'provision_response' => $rawPaymentResponseData,
        ]);
        $mapped3DResponse = $this->map3DPayResponseData($raw3DAuthResponseData);
        $procReturnCode = $mapped3DResponse['proc_return_code'];
        $paymentStatus = 'declined';
        $response = 'Declined';
        if ('approved' === $mapped3DResponse['status']) {
            if ($rawPaymentResponseData->Transaction->Response->ReasonCode === '00') {
                $response = 'Approved';
                $procReturnCode = $rawPaymentResponseData->Transaction->Response->ReasonCode;
                $paymentStatus = 'approved';
            }

            $mappedPaymentResponse = [
                'id'                   => isset($rawPaymentResponseData->Transaction->AuthCode) ? $this->printData($rawPaymentResponseData->Transaction->AuthCode) : null,
                'group_id'             => isset($rawPaymentResponseData->Transaction->SequenceNum) ? $this->printData($rawPaymentResponseData->Transaction->SequenceNum) : null,
                'auth_code'            => isset($rawPaymentResponseData->Transaction->AuthCode) ? $this->printData($rawPaymentResponseData->Transaction->AuthCode) : null,
                'host_ref_num'         => isset($rawPaymentResponseData->Transaction->RetrefNum) ? $this->printData($rawPaymentResponseData->Transaction->RetrefNum) : null,
                'ret_ref_num'          => isset($rawPaymentResponseData->Transaction->RetrefNum) ? $this->printData($rawPaymentResponseData->Transaction->RetrefNum) : null,
                'batch_num'            => isset($rawPaymentResponseData->Transaction->BatchNum) ? $this->printData($rawPaymentResponseData->Transaction->BatchNum) : null,
                'error_code'           => isset($rawPaymentResponseData->Transaction->Response->ErrorCode) ? $this->printData($rawPaymentResponseData->Transaction->Response->ErrorCode) : null,
                'error_message'        => isset($rawPaymentResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawPaymentResponseData->Transaction->Response->ErrorMsg) : null,
                'reason_code'          => isset($rawPaymentResponseData->Transaction->Response->ReasonCode) ? $this->printData($rawPaymentResponseData->Transaction->Response->ReasonCode) : null,
                'campaign_url'         => isset($rawPaymentResponseData->Transaction->CampaignChooseLink) ? $this->printData($rawPaymentResponseData->Transaction->CampaignChooseLink) : null,
                'all'                  => $rawPaymentResponseData,
                'proc_return_code'     => $procReturnCode,
                'code'                 => $procReturnCode,
                'response'             => $response,
                'status'               => $paymentStatus,
                'status_detail'        => $this->getStatusDetail(),
            ];
        }

        if (empty($mappedPaymentResponse)) {
            return array_merge($this->getDefaultPaymentResponse(), $mapped3DResponse);
        }

        return array_merge($mapped3DResponse, $mappedPaymentResponse);
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        $commonResult = $this->tDPayResponseCommon($raw3DAuthResponseData);

        if ('approved' === $commonResult['status']) {
            //these data only available on success
            $commonResult['id'] = $raw3DAuthResponseData['authcode'];
            $commonResult['auth_code'] = $raw3DAuthResponseData['authcode'];
            $commonResult['trans_id'] = $raw3DAuthResponseData['transid'];
            $commonResult['host_ref_num'] = $raw3DAuthResponseData['hostrefnum'];
            $commonResult['rand'] = $raw3DAuthResponseData['rnd'];
            $commonResult['hash_params'] = $raw3DAuthResponseData['hashparams'];
            $commonResult['hash_params_val'] = $raw3DAuthResponseData['hashparamsval'];
            $commonResult['masked_number'] = $raw3DAuthResponseData['MaskedPan'];
            $commonResult['tx_status'] = $raw3DAuthResponseData['txnstatus'];
            $commonResult['eci'] = $raw3DAuthResponseData['eci'];
            $commonResult['cavv'] = $raw3DAuthResponseData['cavv'];
            $commonResult['xid'] = $raw3DAuthResponseData['xid'];
        }

        return $commonResult;
    }

    /**
     * @param array $raw3DAuthResponseData
     *
     * @return array
     */
    protected function tDPayResponseCommon(array $raw3DAuthResponseData): array
    {
        $procReturnCode = $raw3DAuthResponseData['procreturncode'];
        $mdStatus = $raw3DAuthResponseData['mdstatus'];

        $status = 'declined';
        $response = 'Declined';

        $transactionSecurity = 'MPI fallback';
        if (in_array($mdStatus, ['1', '2', '3', '4']) && 'Error' !== $raw3DAuthResponseData['response']) {
            if ('1' === $mdStatus) {
                $transactionSecurity = 'Full 3D Secure';
            } else {
                //['2', '3', '4']
                $transactionSecurity = 'Half 3D Secure';
            }

            $status = 'approved';
            $response = 'Approved';
        }

        return [
            'id'                   => null,
            'order_id'             => $raw3DAuthResponseData['oid'],
            'trans_id'             => null,
            'auth_code'            => null,
            'host_ref_num'         => null,
            'response'             => $response,
            'transaction_type'     => $this->type,
            'transaction'          => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'transaction_security' => $transactionSecurity,
            'proc_return_code'     => $procReturnCode,
            'code'                 => $procReturnCode,
            'md_status'            => $raw3DAuthResponseData['mdstatus'],
            'status'               => $status,
            'status_detail'        => isset($this->codes[$procReturnCode]) ? $procReturnCode : null,
            'hash'                 => $raw3DAuthResponseData['hash'],
            'rand'                 => null,
            'hash_params'          => null,
            'hash_params_val'      => null,
            'masked_number'        => null,
            'amount'               => $raw3DAuthResponseData['txnamount'],
            'currency'             => $raw3DAuthResponseData['txncurrencycode'],
            'tx_status'            => null,
            'eci'                  => null,
            'cavv'                 => null,
            'xid'                  => null,
            'error_code'           => 'Error' === $raw3DAuthResponseData['response'] ? $procReturnCode: null,
            'error_message'        => $raw3DAuthResponseData['errmsg'],
            'md_error_message'     => $raw3DAuthResponseData['mderrormessage'],
            'campaign_url'         => null,
            'email'                => $raw3DAuthResponseData['customeremailaddress'],
            'extra'                => null,
            '3d_all'               => $raw3DAuthResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData): array
    {
        $this->logger->log(LogLevel::DEBUG, 'mapping payment response', [$responseData]);
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        $mappedResponse = [
            'id'               => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'order_id'         => isset($responseData->Order->OrderID) ? $this->printData($responseData->Order->OrderID) : null,
            'group_id'         => isset($responseData->Order->GroupID) ? $this->printData($responseData->Order->GroupID) : null,
            'trans_id'         => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'response'         => isset($responseData->Transaction->Response->Message) ? $this->printData($responseData->Transaction->Response->Message) : null,
            'transaction_type' => $this->type,
            'transaction'      => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'auth_code'        => isset($responseData->Transaction->AuthCode) ? $this->printData($responseData->Transaction->AuthCode) : null,
            'host_ref_num'     => isset($responseData->Transaction->RetrefNum) ? $this->printData($responseData->Transaction->RetrefNum) : null,
            'ret_ref_num'      => isset($responseData->Transaction->RetrefNum) ? $this->printData($responseData->Transaction->RetrefNum) : null,
            'hash_data'        => isset($responseData->Transaction->HashData) ? $this->printData($responseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code'             => $this->getProcReturnCode(),
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => isset($responseData->Transaction->Response->Code) ? $this->printData($responseData->Transaction->Response->Code) : null,
            'error_message'    => isset($responseData->Transaction->Response->ErrorMsg) ? $this->printData($responseData->Transaction->Response->ErrorMsg) : null,
            'campaign_url'     => isset($responseData->Transaction->CampaignChooseLink) ? $this->printData($responseData->Transaction->CampaignChooseLink) : null,
            'extra'            => $responseData->Extra ?? null,
            'all'              => $responseData,
        ];

        $this->logger->log(LogLevel::DEBUG, 'mapped payment response', $mappedResponse);

        return $mappedResponse;
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'id'               => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'order_id'         => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id'         => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id'         => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response'         => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code'        => $rawResponseData->Transaction->AuthCode ?? null,
            'host_ref_num'     => isset($rawResponseData->Transaction->RetrefNum) ? $this->printData($rawResponseData->Transaction->RetrefNum) : null,
            'ret_ref_num'      => isset($rawResponseData->Transaction->RetrefNum) ? $this->printData($rawResponseData->Transaction->RetrefNum) : null,
            'hash_data'        => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code'             => $this->getProcReturnCode(),
            'error_code'       => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message'    => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapCancelResponse($rawResponseData)
    {
        return $this->mapRefundResponse($rawResponseData);
    }

    /**
     * @inheritDoc
     */
    protected function mapStatusResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'id'               => isset($rawResponseData->Order->OrderInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderInqResult->AuthCode) : null,
            'order_id'         => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id'         => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id'         => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response'         => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code'        => isset($rawResponseData->Order->OrderInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderInqResult->AuthCode) : null,
            'host_ref_num'     => isset($rawResponseData->Order->OrderInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderInqResult->RetrefNum) : null,
            'ret_ref_num'      => isset($rawResponseData->Order->OrderInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderInqResult->RetrefNum) : null,
            'hash_data'        => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code'             => $this->getProcReturnCode(),
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message'    => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'extra'            => $rawResponseData->Extra ?? null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        if ($this->getProcReturnCode() === '00') {
            $status = 'approved';
        }

        return (object) [
            'id'               => isset($rawResponseData->Order->OrderHistInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderHistInqResult->AuthCode) : null,
            'order_id'         => isset($rawResponseData->Order->OrderID) ? $this->printData($rawResponseData->Order->OrderID) : null,
            'group_id'         => isset($rawResponseData->Order->GroupID) ? $this->printData($rawResponseData->Order->GroupID) : null,
            'trans_id'         => isset($rawResponseData->Transaction->AuthCode) ? $this->printData($rawResponseData->Transaction->AuthCode) : null,
            'response'         => isset($rawResponseData->Transaction->Response->Message) ? $this->printData($rawResponseData->Transaction->Response->Message) : null,
            'auth_code'        => isset($rawResponseData->Order->OrderHistInqResult->AuthCode) ? $this->printData($rawResponseData->Order->OrderHistInqResult->AuthCode) : null,
            'host_ref_num'     => isset($rawResponseData->Order->OrderHistInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderHistInqResult->RetrefNum) : null,
            'ret_ref_num'      => isset($rawResponseData->Order->OrderHistInqResult->RetrefNum) ? $this->printData($rawResponseData->Order->OrderHistInqResult->RetrefNum) : null,
            'hash_data'        => isset($rawResponseData->Transaction->HashData) ? $this->printData($rawResponseData->Transaction->HashData) : null,
            'proc_return_code' => $this->getProcReturnCode(),
            'code'             => $this->getProcReturnCode(),
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => isset($rawResponseData->Transaction->Response->Code) ? $this->printData($rawResponseData->Transaction->Response->Code) : null,
            'error_message'    => isset($rawResponseData->Transaction->Response->ErrorMsg) ? $this->printData($rawResponseData->Transaction->Response->ErrorMsg) : null,
            'extra'            => $rawResponseData->Extra ?? null,
            'order_txn'        => $rawResponseData->Order->OrderHistInqResult->OrderTxnList->OrderTxn ?? [],
            'all'              => $rawResponseData,
        ];
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode(): ?string
    {
        return isset($this->data->Transaction->Response->Code) ? (string) $this->data->Transaction->Response->Code : null;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail(): ?string
    {
        $procReturnCode = $this->getProcReturnCode();

        return $procReturnCode ? (isset($this->codes[$procReturnCode]) ? (string) $this->codes[$procReturnCode] : null) : null;
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
