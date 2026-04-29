<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Entity\Account;

class PayForAccount extends AbstractPosAccount
{
    public const MBR_ID_FINANSBANK     = '5';
    public const MBR_ID_ZIRAAT_KATILIM = '12';

    /** @var self::MBR_ID_*  */
    private string $mbrId;

    /**
     * AbstractPosAccount constructor.
     *
     * @param string               $bank
     * @param string               $merchantId   Üye işyeri numarası.
     * @param string               $userCode     Otorizasyon sistemi kullanıcı kodu.
     * @param string               $userPassword Otorizasyon sistemi kullanıcı şifresi.
     * @param string|null          $merchantPass 3D Secure şifresidir.
     * @param self::MBR_ID_*       $mbrId        Kurum kodudur.
     */
    public function __construct(
        string  $bank,
        string  $merchantId,
        string  $userCode,
        string  $userPassword,
        ?string $merchantPass = null,
        string  $mbrId = self::MBR_ID_FINANSBANK
    ) {
        $this->mbrId = $mbrId;

        parent::__construct(
            $bank,
            $merchantId,
            $userCode,
            $userPassword,
            $merchantPass
        );
    }

    /**
     * @return self::MBR_ID_*
     */
    public function getMbrId(): string
    {
        return $this->mbrId;
    }
}
