<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

class PayFlexAccount extends AbstractPosAccount
{
    /** @var int */
    public const MERCHANT_TYPE_STANDARD = 0;

    /** @var int */
    public const MERCHANT_TYPE_MAIN_DEALER = 1;

    /** @var int */
    public const MERCHANT_TYPE_SUB_DEALER = 2;

    /** @var int[] */
    private static array $merchantTypes = [
        self::MERCHANT_TYPE_STANDARD,
        self::MERCHANT_TYPE_MAIN_DEALER,
        self::MERCHANT_TYPE_SUB_DEALER,
    ];

    private string $terminalId;

    /**
     * Banka tarafından Üye işyerine iletilmektedir
     * @var self::MERCHANT_TYPE_*
     */
    private int $merchantType;

    /**
     * Ör:00000000000471
     * Alfanumeric. Banka tarafından AltBayiler için üye işyerine iletilecektir.
     * Üye işyeri için bu değer sabittir.
     * MerchantType: 2 ise zorunlu,
     * MerchantType: 0 ise, gönderilmemeli
     * MerchantType: 1 ise, Ana bayi kendi adına işlem geçiyor ise gönderilmemeli,
     * Altbayisi adına işlem geçiyor ise zorunludur.
     */
    private ?string $subMerchantId;

    /**
     * @param string                $bank
     * @param string                $merchantId Isyeri No
     * @param string                $password   Isyeri Sifre
     * @param string                $terminalId Terminal No
     * @param self::MERCHANT_TYPE_* $merchantType
     * @param string|null           $subMerchantId
     */
    public function __construct(
        string $bank,
        string $merchantId,
        string $password,
        string $terminalId,
        int    $merchantType = self::MERCHANT_TYPE_STANDARD,
        string $subMerchantId = null
    ) {
        parent::__construct($bank, $merchantId, '', $password, 'tr');
        $this->terminalId    = $terminalId;
        $this->merchantType  = $merchantType;
        $this->subMerchantId = $subMerchantId;
    }

    /**
     * @return string
     */
    public function getTerminalId(): string
    {
        return $this->terminalId;
    }

    /**
     * @return int
     */
    public function getMerchantType(): int
    {
        return $this->merchantType;
    }

    /**
     * @return string|null
     */
    public function getSubMerchantId(): ?string
    {
        return $this->subMerchantId;
    }

    /**
     * @return bool
     */
    public function isSubBranch(): bool
    {
        return self::MERCHANT_TYPE_SUB_DEALER === $this->merchantType;
    }

    /**
     * @return int[]
     */
    public static function getMerchantTypes(): array
    {
        return self::$merchantTypes;
    }
}
