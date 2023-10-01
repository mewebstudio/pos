<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;

class PayForPosCrypt extends AbstractCrypt
{
    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $hashData = [
            $requestData['MbrId'],
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
        $hashData = [
            $account->getClientId(),
            $account->getStoreKey(),
            $data['OrderId'],
            $data['AuthCode'],
            $data['ProcReturnCode'],
            $data['3DStatus'],
            $data['ResponseRnd'],
            $account->getUsername(),
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        $hash = $this->hashString($hashStr);

        if ($hash === $data['ResponseHash']) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }

        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data'           => $data,
            'generated_hash' => $hash,
            'expected_hash'  => $data['ResponseHash'],
        ]);

        return false;
    }

    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        throw new NotImplementedException();
    }
}
