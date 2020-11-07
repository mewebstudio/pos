<?php

namespace Mews\Pos;

use Exception;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Entity\Card\CreditCardPos;
use Mews\Pos\Exceptions\BankClassNullException;
use Mews\Pos\Exceptions\BankNotFoundException;

/**
 * Class Pos
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
     * @var CreditCardPos
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
        $this->bank = new $class($this->config['banks'][$this->account->bank], $this->account, $this->config['currencies']);
    }

    /**
     * Prepare Order
     *
     * @param array                   $order
     * @param AbstractCreditCard|null $card
     *
     * @return Pos
     */
    public function prepare(array $order, $card = null)
    {
        // Installment
        $installment = 0;
        if (isset($order['installment'])) {
            $installment = $order['installment'] ? (int) $order['installment'] : 0;
        }

        // Currency
        $currency = null;
        if (isset($order['currency'])) {
            $currency = (int) $this->config['currencies'][$order['currency']];
        }

        // Order
        $this->order = (object) array_merge($order, [
            'installment'   => $installment,
            'currency'      => $currency,
        ]);

        // Card
        $this->card = $card;

        // Prepare Order
        $this->bank->prepare($this->order, $this->card);

        return $this;
    }

    /**
     * Make Payment
     *
     * @param AbstractCreditCard $card
     *
     * @return PosInterface
     */
    public function payment($card = null)
    {
        $this->card = $card;

        // Make Payment
        return $this->bank->payment($this->card);
    }

    /**
     * Get gateway URL
     *
     * @return string|null
     */
    public function getGatewayUrl()
    {
        return isset($this->bank->gateway) ? $this->bank->gateway : 'null';
    }

    /**
     * @return array
     */
    public function getConfig(){
        return $this->bank->getConfig();
    }

    /**
     * @return mixed
     */
    public function getAccount(){
        return $this->bank->getAccount();
    }

    /**
     * @return array
     */
    public function getCurrencies(){
        return $this->bank->getCurrencies();
    }

    /**
     * @return mixed
     */
    public function getOrder(){
        return $this->bank->getOrder();
    }

    /**
     * @return AbstractCreditCard
     */
    public function getCard()
    {
        return $this->bank->getCard();
    }

    /**
     * Get 3d Form Data
     *
     * @return array
     */
    public function get3dFormData()
    {
        $data = [];

        try {
            $data = $this->bank->get3dFormData();
        } catch (Exception $e) {}

        return $data;
    }

    /**
     * Is success
     *
     * @return bool
     */
    public function isSuccess()
    {
        return $this->bank->isSuccess();
    }

    /**
     * Is error
     *
     * @return bool
     */
    public function isError()
    {
        return $this->bank->isError();
    }
}
