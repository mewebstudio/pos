<?php

namespace Mews\Pos;

use GuzzleHttp\Exception\GuzzleException;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;

/**
 * Interface PosInterface
 */
interface PosInterface
{
    /**
     * PosInterface constructor.
     *
     * @param object $config
     * @param object $account
     * @param array $currencies
     */
    public function __construct($config, $account, array $currencies);

    /**
     * Create XML DOM Document
     *
     * @param array $nodes
     * @param string $encoding
     * @return string the XML, or false if an error occurred.
     */
    public function createXML(array $nodes, $encoding = 'UTF-8');

    /**
     * Print Data
     *
     * @param $data
     * @return null|string
     */
    public function printData($data);

    /**
     * Regular Payment
     *
     * @return $this
     * @throws GuzzleException
     */
    public function makeRegularPayment();

    /**
     * Make 3D Payment
     *
     * @return $this
     * @throws GuzzleException
     */
    public function make3DPayment();

    /**
     * Make 3D Pay Payment
     *
     * @return $this
     */
    public function make3DPayPayment();

    /**
     * Send contents to WebService
     *
     * @param $contents
     * @return $this
     * @throws GuzzleException
     */
    public function send($contents);

    /**
     * Prepare Order
     *
     * @param object $order
     * @return mixed
     * @throws UnsupportedTransactionTypeException
     */
    public function prepare($order);

    /**
     * Make Payment
     *
     * @param AbstractCreditCard $card
     *
     * @return mixed
     *
     * @throws UnsupportedPaymentModelException
     * @throws GuzzleException
     */
    public function payment($card);

    /**
     * Refund Order
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function refund(array $meta);

    /**
     * Cancel Order
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function cancel(array $meta);

    /**
     * Order Status
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function status(array $meta);

    /**
     * Order History
     *
     * @param array $meta
     * @return $this
     * @throws GuzzleException
     */
    public function history(array $meta);

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess();

    /**
     * Is error
     *
     * @return bool
     */
    public function isError();
}
