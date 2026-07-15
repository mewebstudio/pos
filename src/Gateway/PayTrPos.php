<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Gateway;

use Mews\Pos\DataMapper\Request\Mapper\PayTrPosRequestDataMapper;
use Mews\Pos\DataMapper\Request\Mapper\RequestDataMapperInterface;
use Mews\Pos\DataMapper\Response\Mapper\PayTrPosResponseDataMapper;
use Mews\Pos\DataMapper\Response\Mapper\ResponseDataMapperInterface;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\Model\Card\CreditCardInterface;
use Mews\Pos\Event\RequestDataPreparedEvent;
use Mews\Pos\Exception\HashMismatchException;
use Mews\Pos\Exception\UnsupportedPaymentModelException;
use Mews\Pos\PosInterface;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * @since 2.0.0
 * PayTR Virtual POS gateway.
 * @link https://dev.paytr.com/
 */
class PayTrPos extends AbstractGateway
{
    /** @var string */
    public const NAME = 'PayTrPos';

    /** @var PayTrPosAccount */
    protected AbstractPosAccount $account;

    /** @var PayTrPosRequestDataMapper */
    protected RequestDataMapperInterface $requestDataMapper;

    /** @var PayTrPosResponseDataMapper */
    protected ResponseDataMapperInterface $responseDataMapper;

    protected static bool $testModeAffectsRequests = true;

    /** @inheritdoc */
    protected static array $supportedTransactions = [
        PosInterface::TX_TYPE_PAY_AUTH       => [
            PosInterface::MODEL_3D_HOST,
            PosInterface::MODEL_3D_PAY,
            PosInterface::MODEL_NON_SECURE,
        ],
        PosInterface::TX_TYPE_STATUS         => true,
        PosInterface::TX_TYPE_REFUND         => true,
        PosInterface::TX_TYPE_REFUND_PARTIAL => true,
    ];

    /** @return PayTrPosAccount */
    public function getAccount(): AbstractPosAccount
    {
        return $this->account;
    }

    /**
     * {@inheritDoc}
     *
     * 3DHost: calls the get-token API and returns the iframe embed URL.
     * 3DPay:  builds a form POSTing card data directly to the PayTR Direct API endpoint.
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(
        array                $order,
        string               $paymentModel,
        string               $txType,
        ?CreditCardInterface $creditCard = null,
        bool                 $createWithoutCard = false,
        ?string              $formFormat = null
    ): array {
        $this->check3DFormInputs($paymentModel, $txType, $creditCard, $createWithoutCard);

        /** @var PosInterface::TX_TYPE_PAY_AUTH $txType */
        if (PosInterface::MODEL_3D_HOST === $paymentModel) {
            return $this->initIFramePayment($order, $txType);
        }

        // MODEL_3D_PAY: build a POST form with all card + payment fields
        $this->logger->debug('preparing PayTR 3DPay form data');

        /** @var PosInterface::MODEL_3D_PAY $paymentModel */
        return $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            $creditCard
        );
    }

    /**
     * {@inheritDoc}
     *
     * Not applicable — PayTR does not use a two-step 3DSecure flow.
     */
    public function make3DPayment(array $gatewayResponseData, array $order, string $txType, ?CreditCardInterface $creditCard = null): array
    {
        throw new UnsupportedPaymentModelException();
    }

    /**
     * {@inheritDoc}
     */
    public function make3DPayPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        /**
         * PayTR 3D Auth sonucu olarak
         * Başarılı olduğunda boş array döner, ki bu durumda make3DPayPayment çalıstırılmaması gerekiyor.
         * Başarısız durumda ise sadece fail_message değeri döner. Bu durumda da hash kontrolüne gerek yok.
         * Hash kontrolünü sadece Bildirim URL'e gelen $_POST verisi için çalıştırıyoruz.
         */
        if (count($gatewayResponseData) > 1 && (!$this->is3DHashCheckDisabled() && !$this->crypt->check3DHash($this->account, $gatewayResponseData))) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DPayResponseData($gatewayResponseData, $txType, $order);

        $this->logger->debug('finished 3D pay payment', ['mapped_response' => $this->response]);

        return $this->response;
    }

    /**
     * {@inheritDoc}
     *
     * Processes the PayTR callback notification POST for MODEL_3D_HOST (iFrame).
     * Same callback format as 3DPay.
     */
    public function make3DHostPayment(array $gatewayResponseData, array $order, string $txType): array
    {
        /**
         * PayTR 3D Auth sonucu olarak
         * Başarılı olduğunda boş array döner, ki bu durumda make3DPayPayment çalıstırılmaması gerekiyor.
         * Başarısız durumda ise sadece fail_message değeri döner. Bu durumda da hash kontrolüne gerek yok.
         * Hash kontrolünü sadece Bildirim URL'e gelen $_POST verisi için çalıştırıyoruz.
         */
        if (count($gatewayResponseData) > 1 && (!$this->is3DHashCheckDisabled() && !$this->crypt->check3DHash($this->account, $gatewayResponseData))) {
            throw new HashMismatchException();
        }

        $this->response = $this->responseDataMapper->map3DHostResponseData($gatewayResponseData, $txType, $order);

        $this->logger->debug('finished 3D host payment', ['mapped_response' => $this->response]);

        return $this->response;
    }

    /**
     * Calls the PayTR get-token endpoint to obtain an iFrame token,
     * then returns form data pointing to the iFrame embed URL.
     *
     * @phpstan-param PosInterface::TX_TYPE_PAY_AUTH $txType
     *
     * @param array<string, mixed> $order
     *
     * @return array{gateway: string, method: 'GET', inputs: array<string, string>}
     *
     * @throws ClientExceptionInterface
     * @throws \RuntimeException        when the token API returns an error
     */
    private function initIFramePayment(array $order, string $txType): array
    {
        $this->logger->debug('fetching PayTR iFrame token');

        $paymentModel  = PosInterface::MODEL_3D_HOST;
        $requestTxType = PosInterface::TX_TYPE_INTERNAL_3D_FORM_BUILD;
        $requestData   = $this->requestDataMapper->create3DFormInitializeRequestData(
            $this->account,
            $order,
            $paymentModel,
            $txType
        );

        $event = new RequestDataPreparedEvent(
            $requestData,
            $this->account->getBankName(),
            $requestTxType,
            static::class,
            $order,
            $paymentModel
        );
        /** @var RequestDataPreparedEvent $event */
        $event = $this->eventDispatcher->dispatch($event);
        if ($requestData !== $event->getRequestData()) {
            $this->logger->debug('Request data is changed via listeners', [
                'txType'      => $event->getTxType(),
                'bankName'    => $event->getBankName(),
                'initialData' => $requestData,
                'updatedData' => $event->getRequestData(),
            ]);
            $requestData = $event->getRequestData();
        }

        /** @var array<string, mixed> $tokenResponse */
        $tokenResponse = $this->clientStrategy->getClient(
            $requestTxType,
            $paymentModel
        )->request(
            $requestTxType,
            $paymentModel,
            $requestData,
            $order,
        );

        if ('success' !== ($tokenResponse['status'] ?? null)) {
            $this->logger->error('PayTR iFrame token request failed', $tokenResponse);

            throw new \RuntimeException(
                (string) ($tokenResponse['reason'] ?? 'PayTR iFrame token request failed')
            );
        }

        /** @var array{gateway: string, method: 'GET', inputs: array<string, string>} $formData */
        $formData = $this->requestDataMapper->create3DFormData(
            $this->account,
            $order,
            $paymentModel,
            $txType,
            $this->get3DGatewayURL($paymentModel),
            null,
            $tokenResponse
        );

        return $formData;
    }
}
