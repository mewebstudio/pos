<?php

namespace Mews\Pos;

use Mews\Pos\Exceptions\UnsupportedPaymentModelException;
use Mews\Pos\Exceptions\UnsupportedTransactionTypeException;

/**
 * Interface PosInterface
 * @package Mews\Pos
 */
interface PosInterface
{
    /**
     * PosInterface constructor.
     *
     * @param object $config
     * @param object $account
     * @return $this
     */
    public function __construct($config, $account);

    /**
     * Create 3D Hash
     *
     * @return string
     */
    public function create3DHash();

    /**
     * Check 3D Hash
     *
     * @return bool
     */
    public function check3DHash();

    /**
     * Regular Payment
     *
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function makeRegularPayment();

    /**
     * Make 3D Payment
     *
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @throws \GuzzleHttp\Exception\GuzzleException
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
     * @param object $card
     * @return mixed
     * @throws UnsupportedPaymentModelException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function payment($card);

    /**
     * Refund Order
     *
     * @param $order_id
     * @param null $amount
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function refund($order_id, $amount = null);

    /**
     * Cancel Order
     *
     * @param $order_id
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function cancel($order_id);

    /**
     * Order Status
     *
     * @param $order_id
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function status($order_id);

    /**
     * Order History
     *
     * @param $order_id
     * @return $this
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function history($order_id);
}
