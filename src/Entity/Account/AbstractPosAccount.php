<?php


namespace Mews\Pos\Entity\Account;


use Mews\Pos\Gateways\AbstractGateway;

abstract class AbstractPosAccount
{
    /** @var string */
    protected $clientId;
    
    /**
     * account models: regular, 3d, 3d_pay, 3d_host
     * @var AbstractGateway::MODEL_*
     */
    protected $model;

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;
    
    /**
     * required for non regular account models
     * @var string|null
     */
    protected $storeKey;

    /** @var string */
    protected $lang;
    
    /**
     * bank key name used in configuration file
     *
     * @var string
     */
    protected $bank;

    /**
     * AbstractPosAccount constructor.
     *
     * @param AbstractGateway::MODEL_* $model
     */
    public function __construct(string $bank, string $model, string $clientId, string $username, string $password, string $lang, ?string $storeKey = null)
    {
        $this->model = $model;
        $this->clientId = $clientId;
        $this->username = $username;
        $this->password = $password;
        $this->storeKey = $storeKey;
        $this->lang = $lang;
        $this->bank = $bank;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return AbstractGateway::MODEL_*
     */
    public function getModel(): string
    {
        return $this->model;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getStoreKey(): ?string
    {
        return $this->storeKey;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function getBank(): string
    {
        return $this->bank;
    }
}
