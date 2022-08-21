<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\DataMapper\AbstractRequestDataMapper;
use Mews\Pos\DataMapper\PosNetRequestDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class PosNet
 */
class PosNet extends AbstractGateway
{
    protected const HASH_ALGORITHM = 'sha256';
    protected const HASH_SEPARATOR = ';';
    public const NAME = 'PosNet';

    /**
     * Response Codes
     *
     * @var array
     */
    protected $codes = [
        '0'    => 'declined',
        '1'    => 'approved',
        '2'    => 'declined',
        '00'   => 'approved',
        '0001' => 'bank_call',
        '0005' => 'reject',
        '0007' => 'bank_call',
        '0012' => 'reject',
        '0014' => 'reject',
        '0030' => 'bank_call',
        '0041' => 'reject',
        '0043' => 'reject',
        '0051' => 'reject',
        '0053' => 'bank_call',
        '0054' => 'reject',
        '0057' => 'reject',
        '0058' => 'reject',
        '0062' => 'reject',
        '0065' => 'reject',
        '0091' => 'bank_call',
        '0123' => 'transaction_not_found',
        '0444' => 'bank_call',
    ];

    /** @var PosNetAccount */
    protected $account;

    /** @var AbstractCreditCard|null */
    protected $card;

    /** @var Request */
    protected $request;

    /** @var PosNetCrypt|null */
    private $crypt;

    /** @var PosNetRequestDataMapper */
    protected $requestDataMapper;

    /**
     * @inheritdoc
     *
     * @param PosNetAccount $account
     * @param PosNetRequestDataMapper $requestDataMapper
     */
    public function __construct(
        array $config,
        AbstractPosAccount $account,
        AbstractRequestDataMapper $requestDataMapper,
        LoggerInterface $logger
    ) {
        $this->crypt = new PosNetCrypt();

        parent::__construct($config, $account, $requestDataMapper, $logger);
    }

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
     * @return object
     *
     * @throws GuzzleException
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
        $bankResponse = null;
        if ($this->check3DHash($request->request->all())) {
            $requestData = $this->requestDataMapper->create3DResolveMerchantRequestData(
                $this->account,
                $this->order,
                $request->request->all()
            );

            $contents = $this->createXML($requestData);
            $bankResponse = $this->send($contents);
        } else {
            goto end;
        }

        if ($this->getProcReturnCode() !== '00') {
            goto end;
        }

        if (!$this->verifyResponseMAC($this->account, $this->order, $bankResponse->oosResolveMerchantDataResponse)) {
            goto end;
        }

        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved') {
            //if 3D Authentication is successful:
            if (in_array($bankResponse->oosResolveMerchantDataResponse->mdStatus, [1, 2, 3, 4])) {
                $contents = $this->create3DPaymentXML($request->request->all());
                $bankResponse = $this->send($contents);
            }
        }

        end:
        $this->response = $this->map3DPaymentData($request->request->all(), $bankResponse);

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
            return [];
        }

        $data = $this->getOosTransactionData();
        $data = parent::emptyStringsToNull($data);

        if ('0' === $data['approved']) {
            throw new \Exception($data['respText'], $data['respCode']);
        }

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card, $data['oosRequestDataResponse']);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
    {
        $client = new Client();

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $response = $client->request('POST', $this->getApiURL(), [
            'headers' => $headers,
            'body'    => "xmldata=$contents",
        ]);

        $this->data = $this->XMLStringToObject($response->getBody()->getContents());

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
     * verifies the if request came from bank
     *
     * @param PosNetAccount $account
     * @param               $order
     * @param mixed         $data    oosResolveMerchantDataResponse
     *
     * @return bool
     */
    public function verifyResponseMAC(PosNetAccount $account, $order, $data): bool
    {
        $hashStr = '';

        if ($account->getModel() === self::MODEL_3D_SECURE || $account->getModel() === self::MODEL_3D_PAY) {
            $secondHashData = [
                $data->mdStatus,
                $this->requestDataMapper::formatOrderId($order->id),
                $this->requestDataMapper::amountFormat($order->amount),
                $this->requestDataMapper->mapCurrency($order->currency),
                $account->getClientId(),
                $this->requestDataMapper->createSecurityData($account),
            ];
            $hashStr = implode(static::HASH_SEPARATOR, $secondHashData);
        }

        return $this->hashString($hashStr) === $data->mac;
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
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash(array $data): bool
    {
        if (!($this->crypt instanceof PosNetCrypt)) {
            return false;
        }
        $decryptedString = $this->crypt->decrypt($data['MerchantPacket'], $this->account->getStoreKey());
        if (!$decryptedString) {
            return false;
        }
        $decryptedData = explode(';', $decryptedString);

        $originalData = array_map('strval', [
            $this->account->getClientId(),
            $this->account->getTerminalId(),
            $this->requestDataMapper::amountFormat($this->order->amount),
            $this->order->installment,
            $this->requestDataMapper::formatOrderId($this->order->id),
        ]);

        $decryptedDataList = array_map('strval', [
            $decryptedData[0],
            $decryptedData[1],
            $decryptedData[2],
            ((int) $decryptedData[3]),
            $decryptedData[4],
        ]);

        return $originalData === $decryptedDataList;
    }

    /**
     * Get ProcReturnCode
     *
     * @return string|null
     */
    protected function getProcReturnCode(): ?string
    {
        return (string) $this->data->approved == '1' ? '00' : $this->data->approved;
    }

    /**
     * Get Status Detail Text
     *
     * @return string|null
     */
    protected function getStatusDetail(): ?string
    {
        $procReturnCode = $this->getProcReturnCode();

        return isset($this->codes[$procReturnCode]) ? (string) $this->codes[$procReturnCode] : null;
    }

    /**
     * @inheritDoc
     */
    protected function map3DPaymentData($raw3DAuthResponseData, $rawPaymentResponseData)
    {
        $status = 'declined';
        $transactionSecurity = '';
        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved') {
            if ($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus == '1') {
                $transactionSecurity = 'Full 3D Secure';
                $status = 'approved';
            } elseif (in_array($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus, [2, 3, 4])) {
                $transactionSecurity = 'Half 3D Secure';
                $status = 'approved';
            }
        }

        if ($rawPaymentResponseData->approved != 1) {
            $status = 'declined';
        }

        return (object) [
            'id'                   => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'order_id'             => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'group_id'             => isset($rawPaymentResponseData->groupID) ? $this->printData($rawPaymentResponseData->groupID) : null,
            'trans_id'             => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'response'             => $this->getStatusDetail(),
            'transaction_type'     => $this->type,
            'transaction'          => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'transaction_security' => $transactionSecurity,
            'auth_code'            => isset($rawPaymentResponseData->authCode) ? $this->printData($rawPaymentResponseData->authCode) : null,
            'host_ref_num'         => isset($rawPaymentResponseData->hostlogkey) ? $this->printData($rawPaymentResponseData->hostlogkey) : null,
            'ret_ref_num'          => isset($rawPaymentResponseData->transaction->hostlogkey) ? $this->printData($rawPaymentResponseData->transaction->hostlogkey) : null,
            'proc_return_code'     => $this->getProcReturnCode(),
            'code'                 => $this->getProcReturnCode(),
            'status'               => $status,
            'status_detail'        => $this->getStatusDetail(),
            'error_code'           => !empty($rawPaymentResponseData->respCode) ? $this->printData($rawPaymentResponseData->respCode) : null,
            'error_message'        => !empty($rawPaymentResponseData->respText) ? $this->printData($rawPaymentResponseData->respText) : null,
            'md_status'            => isset($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus) ? $this->printData($rawPaymentResponseData->oosResolveMerchantDataResponse->mdStatus) : null,
            'hash'                 => [
                'merchant_packet' => $raw3DAuthResponseData['MerchantPacket'],
                'bank_packet'     => $raw3DAuthResponseData['BankPacket'],
                'sign'            => $raw3DAuthResponseData['Sign'],
            ],
            'xid'                  => $rawPaymentResponseData->oosResolveMerchantDataResponse->xid ?? null,
            'md_error_message'     => $rawPaymentResponseData->oosResolveMerchantDataResponse->mdErrorMessage ?? null,
            'campaign_url'         => null,
            'all'                  => $rawPaymentResponseData,
            '3d_all'               => $raw3DAuthResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function map3DPayResponseData($raw3DAuthResponseData)
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    protected function mapPaymentResponse($responseData): array
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($responseData->respCode) ? $responseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && $this->getStatusDetail() === 'approved' && $responseData && !$errorCode) {
            $status = 'approved';
            $code = $responseData->approved ?? null;
            $procReturnCode = $this->getProcReturnCode();
        }

        return [
            'id'               => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'order_id'         => $this->order->id,
            'fixed_order_id'   => $this->requestDataMapper::formatOrderId($this->order->id),
            'group_id'         => isset($responseData->groupID) ? $this->printData($responseData->groupID) : null,
            'trans_id'         => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'response'         => $this->getStatusDetail(),
            'transaction_type' => $this->type,
            'transaction'      => empty($this->type) ? null : $this->requestDataMapper->mapTxType($this->type),
            'auth_code'        => isset($responseData->authCode) ? $this->printData($responseData->authCode) : null,
            'host_ref_num'     => isset($responseData->hostlogkey) ? $this->printData($responseData->hostlogkey) : null,
            'ret_ref_num'      => isset($responseData->hostlogkey) ? $this->printData($responseData->hostlogkey) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($responseData->respText) ? $this->printData($responseData->respText) : null,
            'campaign_url'     => null,
            'extra'            => null,
            'all'              => $responseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapRefundResponse($rawResponseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && $rawResponseData && !$errorCode) {
            $status = 'approved';
            $code = $rawResponseData->approved ?? null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;
        $state = $rawResponseData->state ?? null;
        if ('Sale' === $state) {
            $transaction = 'pay';
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        }

        return (object) [
            'id'               => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'fixed_order_id'   => isset($rawResponseData->transaction->orderID) ? $this->printData($rawResponseData->transaction->orderID) : null,
            'group_id'         => isset($rawResponseData->transaction->groupID) ? $this->printData($rawResponseData->transaction->groupID) : null,
            'trans_id'         => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => isset($rawResponseData->transaction->authCode) ? $this->printData($rawResponseData->transaction->authCode) : null,
            'host_ref_num'     => isset($rawResponseData->transaction->hostlogkey) ? $this->printData($rawResponseData->transaction->hostlogkey) : null,
            'ret_ref_num'      => isset($rawResponseData->transaction->hostlogkey) ? $this->printData($rawResponseData->transaction->hostlogkey) : null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transaction->tranDate) ? $this->printData($rawResponseData->transaction->tranDate) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
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
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && isset($rawResponseData->transactions) && !$errorCode) {
            $status = 'approved';
            $code = $rawResponseData->transactions->approved ?? null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;

        $state = null;
        $authCode = null;
        if (isset($rawResponseData->transactions->transaction)) {
            $state = $rawResponseData->transactions->transaction->state ?? null;

            $authCode = isset($rawResponseData->transactions->transaction->authCode) ? $this->printData($rawResponseData->transactions->transaction->authCode) : null;

            if (is_array($rawResponseData->transactions->transaction) && count($rawResponseData->transactions->transaction)) {
                $state = $rawResponseData->transactions->transaction[0]->state;
                $authCode = $rawResponseData->transactions->transaction[0]->authCode;
            }
        }

        if ('Sale' === $state) {
            $transaction = 'pay';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Bonus_Reverse' === $state) {
            $state = 'cancel';
        } else {
            $state = 'mixed';
        }

        return (object) [
            'id'               => $authCode,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'fixed_order_id'   => $this->requestDataMapper::formatOrderId($this->order->id),
            'group_id'         => isset($rawResponseData->transactions->transaction->groupID) ? $this->printData($rawResponseData->transactions->transaction->groupID) : null,
            'trans_id'         => $authCode,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => $authCode,
            'host_ref_num'     => isset($rawResponseData->transactions->transaction->hostLogKey) ? $this->printData($rawResponseData->transactions->transaction->hostLogKey) : null,
            'ret_ref_num'      => null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transactions->transaction->tranDate) ? $this->printData($rawResponseData->transactions->transaction->tranDate) : null,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
            'all'              => $rawResponseData,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function mapHistoryResponse($rawResponseData)
    {
        $status = 'declined';
        $code = '1';
        $procReturnCode = '01';
        $errorCode = !empty($rawResponseData->respCode) ? $rawResponseData->respCode : null;

        if ($this->getProcReturnCode() === '00' && isset($rawResponseData->transactions) && !$errorCode) {
            $status = 'approved';
            $code = $rawResponseData->transactions->approved ?? null;
            $procReturnCode = $this->getProcReturnCode();
        }

        $transaction = null;
        $transactionType = null;

        $state = null;
        $authCode = null;
        $refunds = [];
        if (isset($rawResponseData->transactions->transaction)) {
            $state = $rawResponseData->transactions->transaction->state ?? null;

            $authCode = isset($rawResponseData->transactions->transaction->authCode) ? $this->printData($rawResponseData->transactions->transaction->authCode) : null;

            if (is_array($rawResponseData->transactions->transaction) && count($rawResponseData->transactions->transaction)) {
                $state = $rawResponseData->transactions->transaction[0]->state;
                $authCode = $rawResponseData->transactions->transaction[0]->authCode;

                if (count($rawResponseData->transactions->transaction) > 1) {
                    $currencies = array_flip($this->requestDataMapper->getCurrencyMappings());

                    foreach ($rawResponseData->transactions->transaction as $key => $_transaction) {
                        if ($key > 0) {
                            $currency = isset($currencies[$_transaction->currencyCode]) ?
                                (string) $currencies[$_transaction->currencyCode] :
                                $_transaction->currencyCode;
                            $refunds[] = [
                                'amount'    => (float) $_transaction->amount,
                                'currency'  => $currency,
                                'auth_code' => $_transaction->authCode,
                                'date'      => $_transaction->tranDate,
                            ];
                        }
                    }
                }
            }
        }

        if ('Sale' === $state) {
            $transaction = 'pay';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Authorization' === $state) {
            $transaction = 'pre';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Capture' === $state) {
            $transaction = 'post';
            $state = $transaction;
            $transactionType = $this->requestDataMapper->mapTxType($transaction);
        } elseif ('Bonus_Reverse' === $state) {
            $state = 'cancel';
        } else {
            $state = 'mixed';
        }

        return (object) [
            'id'               => $authCode,
            'order_id'         => isset($this->order->id) ? $this->printData($this->order->id) : null,
            'group_id'         => isset($rawResponseData->transactions->transaction->groupID) ? $this->printData($rawResponseData->transactions->transaction->groupID) : null,
            'trans_id'         => $authCode,
            'response'         => $this->getStatusDetail(),
            'auth_code'        => $authCode,
            'host_ref_num'     => isset($rawResponseData->transactions->transaction->hostLogKey) ? $this->printData($rawResponseData->transactions->transaction->hostLogKey) : null,
            'ret_ref_num'      => null,
            'transaction'      => $transaction,
            'transaction_type' => $transactionType,
            'state'            => $state,
            'date'             => isset($rawResponseData->transactions->transaction->tranDate) ? $this->printData($rawResponseData->transactions->transaction->tranDate) : null,
            'refunds'          => $refunds,
            'proc_return_code' => $procReturnCode,
            'code'             => $code,
            'status'           => $status,
            'status_detail'    => $this->getStatusDetail(),
            'error_code'       => $errorCode,
            'error_message'    => !empty($rawResponseData->respText) ? $this->printData($rawResponseData->respText) : null,
            'extra'            => null,
            'all'              => $rawResponseData,
        ];
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
            'host_ref_num' => $order['host_ref_num'],
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
            //id or host_ref_num
            'id'           => $order['id'] ?? null,
            'host_ref_num' => $order['host_ref_num'] ?? null,
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
            //id or host_ref_num
            'id'           => $order['id'] ?? null,
            'host_ref_num' => $order['host_ref_num'] ?? null,
            'amount'       => $order['amount'],
            'currency'     => $order['currency'] ?? 'TRY',
        ];
    }
}
