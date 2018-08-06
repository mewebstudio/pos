<?php

namespace Mews\Pos;

use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;

/**
 * Class Pos
 * @package Mews\Pos
 */
class Pos
{
    /**
     * Global Configuration
     *
     * @var array
     */
    public $config = [];

    /**
     * API Account
     *
     * @var object
     */
    protected $account;

    /**
     * Order
     *
     * @var object
     */
    protected $order;

    /**
     * Credit Card
     *
     * @var object
     */
    protected $card;

    /**
     * Bank Class
     *
     * @var object
     */
    public $bank;

    /**
     * Pos constructor.
     *
     * @param array $account
     * @param array null $config
     * @throws BankNotFoundException
     * @throws BankClassNullException
     */
    public function __construct(array $account, array $config = null)
    {
        // Get Global Configuration
        $this->config = $config ? $config : require __DIR__ . '/../config/pos.php';

        // API Account
        $this->account = (object) $account;

        // Bank API Exist
        if ( ! array_key_exists($this->account->bank, $this->config['banks'])) {
            throw new BankNotFoundException();
        }

        // Instance Bank Class
        $this->instance();
    }

    /**
     * Instance Bank Class
     *
     * @throws BankClassNullException
     */
    public function instance()
    {
        // Bank Class
        $class = $this->config['banks'][$this->account->bank]['class'];

        if ( ! $class) throw new BankClassNullException();

        // Create Bank Class Object
        $this->bank = new $class($this->config['banks'][$this->account->bank], $this->account);
    }

    /**
     * Prepare Order
     *
     * @param array $order
     * @return Pos
     */
    public function prepare(array $order)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment'])) {
            $installment = $order['installment'] ? (int) $order['installment'] : 0;
        }

        // Currency
        $currency = null;
        if ($order['transaction'] != 'post') {
            $currency = (int) $this->config['currencies'][$order['currency']];
        }

        // Order
        $this->order = (object) array_merge($order, [
            'installment'   => $installment,
            'currency'      => $currency,
        ]);

        // Prepare Order
        $this->bank->prepare($this->order);

        return $this;
    }

    /**
     * Make Payment
     *
     * @param array [] $card
     * @return mixed
     */
    public function payment(array $card = [])
    {
        // Credit Card
        if ($card) {
            $card = array_merge($card, [
                'month'     => str_pad((int) $card['month'], 2, 0, STR_PAD_LEFT),
                'year'      => str_pad((int) $card['year'], 2, 0, STR_PAD_LEFT),
            ]);
        }

        $this->card = (object) $card;

        // Make Payment
        return $this->bank->payment($this->card);
    }
}
