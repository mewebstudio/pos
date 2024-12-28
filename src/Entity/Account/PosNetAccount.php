<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * PosNetAccount
 */
class PosNetAccount extends AbstractPosAccount
{
    public function __construct(
        string $bank,
        string $clientId,
        string $posNetId,
        string $terminalId,
        string $lang,
        ?string $storeKey = null
    ) {
        parent::__construct($bank, $clientId, $posNetId, $terminalId, $lang, $storeKey);
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getPosNetId(): string
    {
        return $this->username;
    }
}
