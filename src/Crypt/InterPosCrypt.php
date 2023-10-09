<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;

class InterPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $hashData = [
            $account->getClientId(),
            $requestData['OrderId'],
            $requestData['PurchAmount'],
            $requestData['OkUrl'],
            $requestData['FailUrl'],
            $requestData['TxnType'],
            $requestData['InstallmentCount'],
            $requestData['Rnd'],
            $account->getStoreKey(),
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $actualHash = $this->hashFromParams($account->getStoreKey(), $data, 'HASHPARAMS', ':');

        if ($data['HASH'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data' => $data,
            'generated_hash' => $actualHash,
            'expected_hash' => $data['HASH'],
        ]);

        return false;
    }

    /**
     * @inheritdoc
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        throw new NotImplementedException();
    }
}
