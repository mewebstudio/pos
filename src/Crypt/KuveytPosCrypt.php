<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

class KuveytPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $hashedPassword = $this->hashString($account->getStoreKey());

        $hashData = [
            $account->getClientId(),
            $requestData['MerchantOrderId'],
            $requestData['Amount'],
            $requestData['OkUrl'],
            $requestData['FailUrl'],
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
            $requestData['MerchantOrderId'],
            $requestData['Amount'],
            $account->getUsername(),
            $hashedPassword,
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
