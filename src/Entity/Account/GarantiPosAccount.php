<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

/**
 * GarantiPosAccount
 */
class GarantiPosAccount extends AbstractPosAccount
{
    private string $terminalId;

    private ?string $refundUsername;

    private ?string $refundPassword;

    public function __construct(
        string $bank,
        string $merchantId,
        string $username,
        string $password,
        string $lang,
        string $terminalId,
        ?string $storeKey = null,
        ?string $refundUsername = null,
        ?string $refundPassword = null
    ) {
        parent::__construct($bank, $merchantId, $username, $password, $lang, $storeKey);
        $this->terminalId = $terminalId;
        $this->refundUsername = $refundUsername;
        $this->refundPassword = $refundPassword;
    }

    /**
     * @return string|null
     */
    public function getRefundPassword(): ?string
    {
        return $this->refundPassword;
    }

    /**
     * @return string|null
     */
    public function getRefundUsername(): ?string
    {
        return $this->refundUsername;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }
}
