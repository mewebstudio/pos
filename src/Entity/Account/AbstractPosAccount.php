<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

use Mews\Pos\PosInterface;

abstract class AbstractPosAccount
{
    protected string $clientId;

    protected string $username;

    protected string $password;

    /**
     * required for non regular account models
     */
    protected ?string $storeKey;

    /** @var PosInterface::LANG_* */
    protected string $lang;

    /**
     * bank key name used in configuration file
     */
    protected string $bank;

    /**
     * AbstractPosAccount constructor.
     *
     * @param string               $bank
     * @param string               $clientId
     * @param string               $username
     * @param string               $password
     * @param PosInterface::LANG_* $lang
     * @param string|null          $storeKey
     */
    public function __construct(string $bank, string $clientId, string $username, string $password, string $lang, ?string $storeKey = null)
    {
        $this->clientId = $clientId;
        $this->username = $username;
        $this->password = $password;
        $this->storeKey = $storeKey;
        $this->lang = $lang;
        $this->bank = $bank;
    }

    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string|null
     */
    public function getStoreKey(): ?string
    {
        return $this->storeKey;
    }

    /**
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * @return string
     */
    public function getBank(): string
    {
        return $this->bank;
    }
}
