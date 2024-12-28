<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;

class ToslaPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha512';

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $data['ClientId'] = $posAccount->getClientId();
        $data['ApiUser']  = $posAccount->getUsername();

        $actualHash = $this->hashFromParams($posAccount->getStoreKey(), $data, 'HashParameters', ',');

        if ($data['Hash'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['Hash'],
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        $hashData = [
            $posAccount->getStoreKey(),
            $requestData['clientId'],
            $requestData['apiUser'],
            $requestData['rnd'],
            $requestData['timeSpan'],
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * @inheritDoc
     */
    protected function concatenateHashKey(string $hashKey, string $hashString): string
    {
        return $hashKey.$hashString;
    }
}
