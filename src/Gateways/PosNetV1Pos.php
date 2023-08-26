<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use LogicException;
use Mews\Pos\DataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
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
    public function getApiURL(): string
    {
        if (null !== $this->type) {
            return parent::getApiURL().'/'.$this->requestDataMapper->mapTxType($this->type);
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
    public function make3DPayment(Request $request)
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
            $contents          = $this->create3DPaymentXML($request->all());
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
    public function get3DFormData(string $paymentModel): array
    {
        if (null === $this->order) {
            $this->logger->log(LogLevel::ERROR, 'tried to get 3D form data without setting order', [
                'order'         => $this->order,
                'card_provided' => (bool) $this->card,
            ]);

            throw new LogicException('Kredi kartı veya sipariş bilgileri eksik!');
        }

        $this->logger->log(LogLevel::DEBUG, 'preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $this->order, $this->type, $this->get3DGatewayURL(), $this->card);
    }

    /**
     * @inheritDoc
     */
    public function send($contents, ?string $url = null)
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
    public function createRegularPaymentXML()
    {
        return $this->requestDataMapper->createNonSecurePaymentRequestData($this->account, $this->order, $this->type, $this->card);
    }

    /**
     * @inheritDoc
     */
    public function createRegularPostXML()
    {
        return $this->requestDataMapper->createNonSecurePostAuthPaymentRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function create3DPaymentXML($responseData)
    {
        return $this->requestDataMapper->create3DPaymentRequestData($this->account, $this->order, $this->type, $responseData);
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
        return $this->requestDataMapper->createStatusRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function createCancelXML()
    {
        return $this->requestDataMapper->createCancelRequestData($this->account, $this->order);
    }

    /**
     * @inheritDoc
     */
    public function createRefundXML()
    {
        return $this->requestDataMapper->createRefundRequestData($this->account, $this->order);
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
            'id'               => $order['id'] ?? null,
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
            'ref_ret_num'      => $order['ref_ret_num'] ?? null,
            'transaction_type' => $order['transaction_type'] ?? AbstractGateway::TX_PAY,
            'amount'           => $order['amount'],
            'currency'         => $order['currency'] ?? 'TRY',
        ];
    }
}
