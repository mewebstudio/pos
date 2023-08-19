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
     * Regular Payment
     *
     * @return AbstractGateway
     */
    public function makeRegularPayment();

    /**
     * Make 3D Payment
     * @param Request $request
     *
     * @return AbstractGateway
     */
    public function make3DPayment(Request $request);

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
     * @param array|string $contents
     * @param string|null  $url
     *
     * @return string|array|null
     */
    public function send($contents, ?string $url = null);

    /**
     * Prepare Order
     *
     * @param array                   $order
     * @param AbstractGateway::TX_*   $txType
     * @param AbstractCreditCard|null $card   need when 3DFormData requested
     *
     * @return void
     */
    public function prepare(array $order, string $txType, AbstractCreditCard $card = null);

    /**
     * Make Payment
     *
     * @param AbstractCreditCard $card
     *
     * @return AbstractGateway
     *
     * @throws UnsupportedPaymentModelException
     */
    public function payment($card);

    /**
     * Refund Order
     *
     * @return AbstractGateway
     */
    public function refund();

    /**
     * Cancel Order
     *
     * @return AbstractGateway
     */
    public function cancel();

    /**
     * Order Status
     *
     * @return AbstractGateway
     */
    public function status();

    /**
     * Order History
     *
     * @param array $meta
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
