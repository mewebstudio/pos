<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\PosNetV1PosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\PosNetV1PosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;

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
    public function make3DHostPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * Kullanıcı doğrulama sonucunun sorgulanması ve verilerin doğruluğunun teyit edilmesi için kullanılır.
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null): PosInterface
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
        if ('1' !== $mdStatus) {
            $this->logger->error('3d auth fail', ['md_status' => $mdStatus]);
        } else {
            $this->logger->debug('finishing payment', ['md_status' => $mdStatus]);
            $requestData       = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

            $event = new RequestDataPreparedEvent($requestData, $this->account->getBank(), $txType);
            $this->eventDispatcher->dispatch($event);
            if ($requestData !== $event->getRequestData()) {
                $this->logger->debug('Request data is changed via listeners', [
                    'txType'      => $event->getTxType(),
                    'bank'        => $event->getBank(),
                    'initialData' => $requestData,
                    'updatedData' => $event->getRequestData(),
                ]);
                $requestData = $event->getRequestData();
            }

            $contents          = $this->serializer->encode($requestData, $txType);
            $provisionResponse = $this->send($contents, $txType);
            $this->logger->debug('send $provisionResponse', ['$provisionResponse' => $provisionResponse]);
        }

        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $provisionResponse);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

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
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $this->get3DGatewayURL(), $card);
    }

    /**
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, ?string $url = null): array
    {
        $url = $this->getApiURL();
        $this->logger->debug('sending request', ['url' => $url]);

        if (!is_string($contents)) {
            throw new InvalidArgumentException('Invalid data provided');
        }
        $body = $contents;

        $response = $this->client->post($url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => $body,
        ]);

        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        try {
            return $this->data = $this->serializer->decode($response->getBody(), $txType);
        } catch (NotEncodableValueException $e) {
            $this->logger->error('parsing bank JSON response failed', [
                'status_code' => $response->getStatusCode(),
                'response'    => $response->getBody(),
                'message'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
