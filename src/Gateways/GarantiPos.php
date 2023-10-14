<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Mews\Pos\DataMapper\RequestDataMapper\GarantiPosRequestDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\GarantiPosResponseDataMapper;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\HashMismatchException;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class GarantiPos
 */
class GarantiPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'GarantiPay';

    /** @var GarantiPosAccount */
    protected $account;

    /** @var GarantiPosRequestDataMapper */
    protected $requestDataMapper;

    /** @var GarantiPosResponseDataMapper */
    protected $responseDataMapper;

    /** @inheritdoc */
    protected static $supportedTransactions = [
        PosInterface::TX_PAY      => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_PRE_PAY  => true,
        PosInterface::TX_POST_PAY => true,
        PosInterface::TX_STATUS   => true,
        PosInterface::TX_CANCEL   => true,
        PosInterface::TX_REFUND   => true,
        PosInterface::TX_HISTORY  => true,
    ];


    /** @return GarantiPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null): PosInterface
    {
        $request = $request->request;
        $bankResponse = null;

        // mdstatus 7 oldugunda hash, hashparam degerler gelmiyor, dolasiyla check3dhash calismiyor
        if ($request->get('mdstatus') !== '7' && !$this->requestDataMapper->getCrypt()->check3DHash($this->account, $request->all())) {
            throw new HashMismatchException();
        }

        if (in_array($request->get('mdstatus'), [1, 2, 3, 4])) {
            $this->logger->debug('finishing payment', ['md_status' => $request->get('mdstatus')]);

            $requestData  = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $request->all());

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
        } else {
            $this->logger->error('3d auth fail', ['md_status' => $request->get('mdstatus')]);
        }


        $this->response = $this->responseDataMapper->map3DPaymentData($request->all(), $bankResponse);
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
    public function get3DFormData(array $order, string $paymentModel, string $txType, AbstractCreditCard $card = null): array
    {
        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData($this->account, $order, $paymentModel, $txType, $this->get3DGatewayURL(), $card);
    }

    /**
     * TODO implement
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request): PosInterface
    {
        throw new UnsupportedPaymentModelException();
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

        $response = $this->client->post($url, ['body' => $contents]);
        $this->logger->debug('request completed', ['status_code' => $response->getStatusCode()]);

        return $this->data = $this->serializer->decode($response->getBody()->getContents(), $txType);
    }
}
