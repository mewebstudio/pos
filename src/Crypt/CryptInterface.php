<?php

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

interface CryptInterface
{
    /**
     * check hash of 3D secure response
     *
     * @param array<string, string> $data
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool;

    /**
     * creates hash for 3D secure payments
     *
     * @param array<string, string> $requestData
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string;

    /**
     * create hash for non-3D actions
     *
     * @param array<string, string> $requestData
     */
    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string;
}
