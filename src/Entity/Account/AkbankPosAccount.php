<?php

/**
 * @license
 */

namespace Mews\Pos\Entity\Account;

class AkbankPosAccount extends AbstractPosAccount
{
    private ?string $subMerchantId;

    /**
     * @param string      $bank
     * @param string      $merchantSafeId Üye İş Yeri numarası
     * @param string      $terminalSafeId
     * @param string      $secretKey
     * @param string|null $subMerchantId
     */
    public function __construct(
        string $bank,
        string $merchantSafeId,
        string $terminalSafeId,
        string $secretKey,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bank, $merchantSafeId, $terminalSafeId, '', $secretKey);
        $this->subMerchantId = $subMerchantId;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->username;
    }

    /**
     * @return string|null
     */
    public function getSubMerchantId(): ?string
    {
        return $this->subMerchantId;
    }
}
