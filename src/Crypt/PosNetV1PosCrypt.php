<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;

class PosNetV1PosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = '';

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string
    {
        $hashData = [
            $account->getClientId(),
            $account->getTerminalId(),
            $requestData['CardNo'],
            $requestData['Cvv'],
            $requestData['ExpiredDate'],
            $requestData['Amount'],
            $account->getStoreKey(),
        ];
        $hashStr  = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        if (null === $account->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }
        
        $actualHash = $this->hashFromParams($account->getStoreKey(), $data, 'MacParams', ':');

        if ($actualHash !== $data['Mac']) {
            $this->logger->error('hash check failed', [
                'order_id' => $data['OrderId'],
            ]);

            return false;
        }

        $this->logger->debug('hash check is successful', [
            'order_id' => $data['OrderId'],
        ]);

        return true;
    }

    /**
     * @param PosNetAccount $account
     * @param array<string, string|array<string, string>> $requestData
     *
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        /** @var array<string, string> $threeDSecureData */
        $threeDSecureData = $requestData['ThreeDSecureData'];
        $hashData = [
            $account->getClientId(),
            $account->getTerminalId(),
            $threeDSecureData['SecureTransactionId'],
            $threeDSecureData['CavvData'],
            $threeDSecureData['Eci'],
            $threeDSecureData['MdStatus'],
            $account->getStoreKey(),
        ];

        $hashStr = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
