<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

class ToslaPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha512';

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $hashData = [
            $account->getStoreKey(),
            $account->getClientId(),
            $account->getUsername(),
            $requestData['rnd'],
            $requestData['timeSpan'],
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        if (null === $account->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $data['ClientId'] = $account->getClientId();
        $data['ApiUser'] = $account->getUsername();

        $actualHash = $this->hashFromParams($account->getStoreKey(), $data, 'HashParameters', ',');

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
     * @param AbstractPosAccount   $account
     * @param array<string, mixed> $requestData
     *
     * @return string
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        return $this->create3DHash($account, $requestData);
    }

    /**
     * @inheritDoc
     */
    protected function concatenateHashKey(string $hashKey, string $hashString): string
    {
        return $hashKey.$hashString;
    }
}
