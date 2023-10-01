<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;

class EstV3PosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha512';

    /** @var string */
    protected const HASH_SEPARATOR = '|';

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        ksort($requestData, SORT_NATURAL | SORT_FLAG_CASE);
        foreach (array_keys($requestData) as $key) {
            // this part is needed only to create hash from the bank response
            if (in_array(strtolower($key), ['hash', 'encoding']))  {
                unset($requestData[$key]);
            }
        }

        $requestData[] = $account->getStoreKey();
        // escape | and \ characters
        $data = str_replace("\\", "\\\\", array_values($requestData));
        $data = str_replace(self::HASH_SEPARATOR, "\\".self::HASH_SEPARATOR, $data);

        $hashStr = implode(self::HASH_SEPARATOR, $data);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $actualHash = $this->create3DHash($account, $data);

        if ($data['HASH'] === $actualHash) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }

        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data' => $data,
            'generated_hash' => $actualHash,
            'expected_hash' => $data['HASH']
        ]);

        return false;
    }

    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        throw new NotImplementedException();
    }
}
