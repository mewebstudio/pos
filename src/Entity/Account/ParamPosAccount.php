<?php

/**
 * @license
 */

namespace Mews\Pos\Entity\Account;

use Mews\Pos\PosInterface;

class ParamPosAccount extends AbstractPosAccount
{
    /**
     * @param string $bank
     * @param int    $clientId  CLIENT_CODE Terminal ID
     * @param string $username  CLIENT_USERNAME Kullanıcı adı
     * @param string $password  CLIENT_PASSWORD Şifre
     * @param string $secretKey GUID  Üye İşyeri ait anahtarı
     */
    public function __construct(
        string $bank,
        int    $clientId,
        string $username,
        string $password,
        string $secretKey
    ) {
        parent::__construct($bank, (string) $clientId, $username, $password, PosInterface::LANG_TR, $secretKey);
    }
}
