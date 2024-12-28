<?php

/**
 * @license
 */

namespace Mews\Pos\Entity\Account;

use Mews\Pos\PosInterface;

class AkbankPosAccount extends AbstractPosAccount
{
    private ?string $subMerchantId;

    /**
     * @phpstan-param PosInterface::LANG_* $lang
     *
     * @param string      $bank
     * @param string      $merchantSafeId Üye İş Yeri numarası
     * @param string      $terminalSafeId
     * @param string      $secretKey
     * @param string      $lang
     * @param string|null $subMerchantId
     */
    public function __construct(
        string $bank,
        string $merchantSafeId,
        string $terminalSafeId,
        string $secretKey,
        string $lang,
        ?string $subMerchantId = null
    ) {
        parent::__construct($bank, $merchantSafeId, $terminalSafeId, '', $lang, $secretKey);
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
