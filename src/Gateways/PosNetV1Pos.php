<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Request;

class PosNetV1Pos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PosNetV1';

    /** @var PosNetAccount */
    protected $account;

    /** @var PosNetV1PosRequestDataMapper */
    protected $requestDataMapper;


    /** @var PosNetV1PosResponseDataMapper */
    protected $responseDataMapper;

    /** @return PosNetAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function getApiURL(string $txType = null): string
    {
        if (null !== $txType) {
            return parent::getApiURL().'/'.$this->requestDataMapper->mapTxType($txType);
        }

        return parent::getApiURL();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request)
    {
        throw new NotImplementedException();
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null)
    {
        $request           = $request->request;
        $provisionResponse = null;
        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        $mdStatus = $request->get('MdStatus');
        /**
         * MdStatus degerleri:
         *   0: Kart doğrulama başarısız, işleme devam etmeyin
         *   1: Doğrulama başarılı, işleme devam edebilirsiniz
         *   2: Kart sahibi veya bankası sisteme kayıtlı değil
         *   3: Kartın bankası sisteme kayıtlı değil
         *   4: Doğrulama denemesi, kart sahibi sisteme daha sonra kayıt olmayı seçmiş
         *   5: Doğrulama yapılamıyor
         *   6: 3D Secure hatası
         *   7: Sistem hatası
         *   8: Bilinmeyen kart no
         *   9: Üye İşyeri 3D-Secure sistemine kayıtlı değil (bankada işyeri ve terminal numarası 3d olarak tanımlı değil.)
         */
        if ($mdStatus !== '1') {
            $this->logger->log(LogLevel::ERROR, '3d auth fail', ['md_status' => $mdStatus]);
        } else {
            $this->logger->log(LogLevel::DEBUG, 'finishing payment', ['md_status' => $mdStatus]);
            $contents          = $this->create3DPaymentXML($request->all(), $order, $txType);
            $provisionResponse = $this->send($contents);
            $this->logger->log(LogLevel::DEBUG, 'send $provisionResponse', ['$provisionResponse' => $provisionResponse]);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $provisionResponse);
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
        $preparedOrder = $this->preparePaymentOrder($order);

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $preparedOrder, $paymentModel, $txType, $this->get3DGatewayURL(), $card);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, string $txType = null, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->log(LogLevel::DEBUG, 'sending request', ['url' => $url]);

        $body = json_encode($contents);
        if (false === $body) {
            throw new \DomainException('Invalid data provided');
        }

        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => $body,
        ]);

        $this->logger->log(LogLevel::DEBUG, 'request completed', ['status_code' => $response->getStatusCode()]);

        try {
            $this->data = json_decode($response->getBody(), true);
        } catch (\Throwable $throwable) {
            $this->logger->log(LogLevel::ERROR, 'parsing bank JSON response failed', [
                'status_code' => $response->getStatusCode(),
                'response'    => $response->getBody(),
                'message'     => $throwable->getMessage(),
            ]);

            throw $throwable;
        }

        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function createRegularPaymentXML(array $order, AbstractCreditCard $card, string $txType): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        return $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $preparedOrder, $txType, $card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML(array $order): array
    {
        $preparedOrder = $this->preparePostPaymentOrder($order);

        return $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML(array $responseData, array $order, string $txType, AbstractCreditCard $card = null): array
    {
        $preparedOrder = $this->preparePaymentOrder($order);

        return $this->requestDataMapper->create3DPaymentRequestData($this->account, $preparedOrder, $txType, $responseData);
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
    public function createStatusXML(array $order): array
    {
        $preparedOrder = $this->prepareStatusOrder($order);

        return $this->requestDataMapper->createStatusRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML(array $order): array
    {
        $preparedOrder = $this->prepareCancelOrder($order);

        return $this->requestDataMapper->createCancelRequestData($this->account, $preparedOrder);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML(array $order): array
    {
        $preparedOrder = $this->prepareRefundOrder($order);

        return $this->requestDataMapper->createRefundRequestData($this->account, $preparedOrder);
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
            'id'          => $order['id'],
            'amount'      => $order['amount'],
            'installment' => $order['installment'] ?? 0,
            'currency'    => $order['currency'] ?? 'TRY',
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
        return (object) [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? self::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? AbstractGateway::TX_PAY,
        ];
    }

    /**
     * @inheritDoc
     */
    protected function prepareRefundOrder(array $order)
    {
        return (object) [
            //id or ref_ret_num
            'id'               => $order['id'] ?? null,
            'payment_model'    => $order['payment_model'] ?? self::MODEL_3D_SECURE,
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? AbstractGateway::TX_PAY,
            'amount'           => $order['amount'],
            'currency'         => $order['currency'] ?? 'TRY',
        ];
    }
}
