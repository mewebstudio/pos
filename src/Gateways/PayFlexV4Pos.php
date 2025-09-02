<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateways;

use Exception;
use Mews\Pos\DataMapper\RequestDataMapper\PayFlexV4PosRequestDataMapper;
use Mews\Pos\DataMapper\RequestDataMapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\ResponseDataMapper\PayFlexV4PosResponseDataMapper;
use Mews\Pos\DataMapper\ResponseDataMapper\ResponseDataMapperInterface;
use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;
use Mews\Pos\PosInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PayFlex MPI ISD v4 gateway'i destekler (INNOVA BİLİŞİM ÇÖZÜMLERİ A.Ş)
 * Dokumanlar: http://sanalpos.innova.com.tr/
 *
 * VakıfBank VPOS 7/24
 */
class PayFlexV4Pos extends AbstractHttpGateway
{
    /** @var string */
    public const NAME = 'PayFlexV4';

    /** @var PayFlexAccount */
    protected AbstractPosAccount $account;

    /** @var PayFlexV4PosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PayFlexV4PosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_PRE_AUTH   => [
            PosInterface::MODEL_3D_SECURE,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_PAY_POST_AUTH  => true,
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_CANCEL         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
        PosInterface::TX_TYPE_HISTORY        => false,
        PosInterface::TX_TYPE_ORDER_HISTORY  => false,
        PosInterface::TX_TYPE_CUSTOM_QUERY   => true,
    ];

    /** @return PayFlexAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayment(Request $request, array $order, string $txType, CreditCardInterface $creditCard = null): PosInterface
    {
        $postParameters = $request->request;
        $paymentModel   = PosInterface::MODEL_3D_SECURE;

        if (!$this->is3DAuthSuccess($postParameters->all())) {
            $this->response = $this->responseDataMapper->map3DPaymentData($postParameters->all(), null, $txType, $order);

            return $this;
        }

        /** @var array{Eci: string, Cavv: string, VerifyEnrollmentRequestId: string} $requestData */
        $requestData = $postParameters->all();
        // NOT: diger gatewaylerden farkli olarak payflex kredit bilgilerini bu asamada da istiyor.
        $requestData = $this->requestDataMapper->create3DPaymentRequestData($this->account, $order, $txType, $requestData, $creditCard);

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        $bankResponse = $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
        );

        $this->response = $this->responseDataMapper->map3DPaymentData($postParameters->all(), $bankResponse, $txType, $order);
        $this->logger->debug('finished 3D payment', ['mapped_response' => $this->response]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function make3DPayPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function make3DHostPayment(Request $request, array $order, string $txType): PosInterface
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * @inheritDoc
     */
    public function history(array $data): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * @inheritDoc
     */
    public function orderHistory(array $order): PosInterface
    {
        throw new UnsupportedTransactionTypeException();
    }

    /**
     * {@inheritDoc}
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, CreditCardInterface $creditCard = null, bool $createWithoutCard = true): array
    {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard);

        $data = $this->sendEnrollmentRequest($order, $creditCard, $txType, $paymentModel);

        $status = $data['Message']['VERes']['Status'];
        /**
         * Status values:
         * Y:Kart 3-D Secure programına dâhil
         * N:Kart 3-D Secure programına dâhil değil
         * U:İşlem gerçekleştirilemiyor
         * E:Hata durumu
         */
        if ('E' === $status) {
            $this->logger->error('enrollment fail response', $data);
            throw new \RuntimeException($data['ErrorMessage'], $data['MessageErrorCode']);
        }

        if ('N' === $status) {
            //half secure olarak devam et yada satisi iptal et.
            $this->logger->error('enrollment fail response', $data);
            throw new \RuntimeException('Kart 3-D Secure programına dâhil değil');
        }

        if ('U' === $status) {
            $this->logger->error('enrollment fail response', $data);
            throw new \RuntimeException('İşlem gerçekleştirilemiyor');
        }

        $this->logger->debug('preparing 3D form data');

        return $this->requestDataMapper->create3DFormData(
            $this->account,
            null,
            $paymentModel,
            $txType,
            '',
            null,
            $data['Message']['VERes']
        );
    }

    /**
     * Müşteriden kredi kartı bilgilerini aldıktan sonra GET 7/24 MPI’a kart “Kredi Kartı Kayıt Durumu”nun
     * (Enrollment Status) sorulması, yani kart 3-D Secure programına dâhil mi yoksa değil mi sorgusu
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH|PosInterface::TX_TYPE_PAY_PRE_AUTH $txType
     * @phpstan-param PosInterface::MODEL_3D_*                                          $paymentModel
     *
     * @param array<string, int|string|float|null> $order
     * @param CreditCardInterface                  $creditCard
     * @param string                               $txType
     * @param string                               $paymentModel
     *
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    private function sendEnrollmentRequest(array $order, CreditCardInterface $creditCard, string $txType, string $paymentModel): array
    {
        $requestData = $this->requestDataMapper->create3DEnrollmentCheckRequestData(
            $this->account,
            $order,
            $creditCard
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBank(),
            $txType,
            \get_class($this),
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bank'        => $event->getBank(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        return $this->client->request(
            $txType,
            $paymentModel,
            $requestData,
            $order,
            $this->get3DGatewayURL(),
            null,
            false
        );
    }
}
