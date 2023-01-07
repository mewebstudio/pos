<?php

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

interface CryptInterface
{
    /**
     * check hash of 3D secure response
     *
     * @param AbstractPosAccount    $account
     * @param array<string, string> $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool;

    /**
     * creates hash for 3D secure payments
     *
     * @param AbstractPosAccount    $account
     * @param array<string, string> $requestData
     * @param string|null           $txType
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string;

    /**
     * create hash for non-3D actions
     *
     * @param AbstractPosAccount      $account
     * @param array<string, string>   $requestData
     * @param string|null             $txType
     * @param AbstractCreditCard|null $card
     *
     * @return string
     */
    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string;
}
