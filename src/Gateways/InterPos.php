<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use InvalidArgumentException;
use Mews\Pos\DataMapper\RequestDataMapper\InterPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\InterPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;
use function gettype;
use function is_array;
use function sprintf;

/**
 * Deniz bankin desteklidigi Gateway
 * Class InterPos
 */
class InterPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'InterPos';

    /** @var InterPosAccount */
    protected $account;

    /** @var InterPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var InterPosResponseDataMapper */
    protected $responseDataMapper;

    /** @return InterPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null): PosInterface
    {
        $bankResponse = null;
        $request      = $request->request;
        /** @var array{MD: string, PayerTxnId: string, Eci: string, PayerAuthenticationCode: string} $gatewayResponse */
        $gatewayResponse = $request->all();

        if (!$this->requestDataMapper->getCrypt()->check3DHash($this->account, $gatewayResponse)) {
            throw new HashMismatchException();
        }

        if ('1' !== $request->get('3DStatus')) {
            $this->logger->error('3d auth fail', ['md_status' => $request->get('3DStatus')]);
            /**
             * TODO hata durumu ele alinmasi gerekiyor
             */
        } else {
            $this->logger->debug('finishing payment');

            $requestData  = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $gatewayResponse);

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

            $contents     = $this->serializer->encode($requestData, $txType);
            $bankResponse = $this->send($contents, $txType);
        }


        $this->response = $this->responseDataMapper->map3DPaymentData($gatewayResponse, $bankResponse);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request): PosInterface
    {
        $this->response = $this->responseDataMapper->map3DPayResponseData($request->request->all());

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        return $this->make3DPayPayment($request);
    }

    /**
     * Deniz bank dokumantasyonunda history sorgusu ile alakali hic bir bilgi yok
     * @inheritDoc
     */
    public function history(array $meta): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $gatewayUrl = $this->get3DHostGatewayURL();

        if (PosInterface::MODEL_3D_SECURE === $paymentModel || PosInterface::MODEL_3D_PAY === $paymentModel) {
            $gatewayUrl = $this->get3DGatewayURL();
        }

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $gatewayUrl, $card);
    }

    /**
     * @inheritDoc
     *
     * @return array<string, mixed>
     */
    protected function send($contents, string $txType, ?string $url = null): array
    {
        $url = $url ?: $this->getApiURL();
        $this->logger->debug('sending request', ['url' => $url]);
        if (!is_array($contents)) {
            throw new InvalidArgumentException(sprintf('Argument type must be array, %s provided.', gettype($contents)));
        }

        $response = $this->client->post($url, ['form_params' => $contents]);
        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }
}
