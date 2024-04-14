<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

class KuveytPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $hashedPassword = $this->hashString($posAccount->getStoreKey());

        $hashData = [
            $posAccount->getClientId(),
            $requestData['MerchantOrderId'],
            $requestData['Amount'],
            $requestData['OkUrl'],
            $requestData['FailUrl'],
            $posAccount->getUsername(),
            $hashedPassword,
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $hashedPassword = $this->hashString($posAccount->getStoreKey());

        $hashData = [
            $posAccount->getClientId(),
            // non-payment request may not have MerchantOrderId and Amount fields
            $requestData['MerchantOrderId'] ?? '',
            $requestData['Amount'] ?? '',
            $posAccount->getUsername(),
            $hashedPassword,
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
