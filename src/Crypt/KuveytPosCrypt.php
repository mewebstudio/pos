<?php

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

class KuveytPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string
    {
        $hashedPassword = $this->hashString($account->getStoreKey());

        $hashData = [
            $account->getClientId(),
            $requestData['id'],
            $requestData['amount'],
            $requestData['success_url'],
            $requestData['fail_url'],
            $account->getUsername(),
            $hashedPassword,
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * todo implement
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string
    {
        $hashedPassword = $this->hashString($account->getStoreKey());

        $hashData = [
            $account->getClientId(),
            $requestData['id'],
            $requestData['amount'],
            $account->getUsername(),
            $hashedPassword,
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
