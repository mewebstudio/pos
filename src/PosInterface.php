<?php
/**
 * @license MIT
 */
namespace Mews\Pos;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Gateways\AbstractGateway;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface PosInterface
 */
interface PosInterface
{
    /**
     * Create XML DOM Document
     *
     * @param array  $nodes
     * @param string $encoding
     * @param bool   $ignorePiNode when true it will not wrap it with this node <?xml version="1.0" encoding="UTF-8"?>
     *
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, string $encoding = 'UTF-8', bool $ignorePiNode = false);

    /**
     * returns form data, key values, necessary for 3D payment
     *
     * @param array<string, mixed>                                $order
     * @param AbstractGateway::MODEL_*                            $paymentModel
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     * @param AbstractCreditCard|null                             $card
     *
     * @return array{gateway: string, method: 'POST'|'GET', inputs: array<string, string>}
     */
    public function get3DFormData(array $order, string $paymentModel, string $txType, ?AbstractCreditCard $card = null): array;

    /**
     * Regular Payment
     *
     * @param array<string, mixed>                                                             $order
     * @param AbstractCreditCard                                                               $card
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY|AbstractGateway::TX_POST_PAY $txType
     *
     * @return AbstractGateway
     */
    public function makeRegularPayment(array $order, AbstractCreditCard $card, string $txType);

    /**
     * Make 3D Payment
     *
     * @param Request                                             $request
     * @param array<string, mixed>                                $order
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY $txType
     * @param AbstractCreditCard                                  $card simdilik sadece PayFlexV4Pos icin card isteniyor.
     *
     * @return AbstractGateway
     */
    public function make3DPayment(Request $request, array $order, string $txType, AbstractCreditCard $card = null);

    /**
     * Make 3D Pay Payment
     * @param Request $request
     *
     * @return AbstractGateway
     */
    public function make3DPayPayment(Request $request);

    /**
     * Just returns formatted data of host payment response
     * @param Request $request
     *
     * @return AbstractGateway
     */
    public function make3DHostPayment(Request $request);

    /**
     * Send contents to WebService
     *
     * @param array<string, mixed>|string          $contents
     * @param AbstractGateway::TX_* $txType
     * @param string|null           $url
     *
     * @return string|array|null
     */
    public function send($contents, string $txType = null, ?string $url = null);

    /**
     * Make Payment
     *
     * @param AbstractGateway::MODEL_*                                                         $paymentModel
     * @param array<string, mixed>                                                             $order
     * @param AbstractGateway::TX_PAY|AbstractGateway::TX_PRE_PAY|AbstractGateway::TX_POST_PAY $txType
     * @param AbstractCreditCard                                                               $card
     *
     * @return AbstractGateway
     *
     * @throws UnsupportedPaymentModelException
     */
    public function payment(string $paymentModel, array $order, string $txType, AbstractCreditCard $card);

    /**
     * Refund Order
     * @param array<string, mixed> $order
     *
     * @return AbstractGateway
     */
    public function refund(array $order);

    /**
     * Cancel Order
     * @param array<string, mixed> $order
     *
     * @return AbstractGateway
     */
    public function cancel(array $order);

    /**
     * Order Status
     * @param array<string, mixed> $order
     *
     * @return AbstractGateway
     */
    public function status(array $order);

    /**
     * Order History
     *
     * @param array<string, mixed> $meta
     *
     * @return AbstractGateway
     */
    public function history(array $meta);

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess();

    /**
     * Enable/Disable test mode
     *
     * @param bool $testMode
     *
     * @return AbstractGateway
     */
    public function setTestMode(bool $testMode);

    /**
     * Enable/Disable test mode
     *
     * @return bool
     */
    public function isTestMode();

    /**
     * @return array<AbstractCreditCard::CARD_TYPE_*, string>
     */
    public function getCardTypeMapping(): array;

    /**
     * @return AbstractPosAccount
     */
    public function getAccount(): AbstractPosAccount;
}
