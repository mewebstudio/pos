<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

/**
 * Dummy crypt that can be used if there is no cryptography logic needed.
 */
class NullCrypt implements CryptInterface
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function hashFromParams(string $storeKey, array $data, string $hashParamsKey, string $paramSeparator): string
    {
        return '';
    }
}
