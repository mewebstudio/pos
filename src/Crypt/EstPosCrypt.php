<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;

class EstPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $hashData = [
            $account->getClientId(),
            $requestData['oid'],
            $requestData['amount'],
            $requestData['okUrl'],
            $requestData['failUrl'],
            $requestData['islemtipi'],
            $requestData['taksit'],
            $requestData['rnd'],
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
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }

        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['HASH'],
        ]);

        return false;
    }

    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        throw new NotImplementedException();
    }
}
