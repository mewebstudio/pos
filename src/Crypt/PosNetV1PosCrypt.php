<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

class PosNetV1PosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = '';

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs, ?string $txType = null): string
    {
        $hashData = [
            $formInputs['MerchantNo'],
            $formInputs['TerminalNo'],
            // no card data for 3D host payment
            $formInputs['CardNo'] ?? null,
            $formInputs['Cvv'] ?? null,
            $formInputs['ExpiredDate'] ?? null,

            $formInputs['Amount'],
            $posAccount->getStoreKey(),
        ];
        $hashStr  = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $actualHash = $this->hashFromParams($posAccount->getStoreKey(), $data, 'MacParams', ':');

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
     * @param array<string, string|array<string, string>> $requestData
     *
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        /** @var array<string, string> $threeDSecureData */
        $threeDSecureData = $requestData['ThreeDSecureData'];

        /** @var array<string, string> $hashData */
        $hashData = [
            $requestData['MerchantNo'],
            $requestData['TerminalNo'],
            $threeDSecureData['SecureTransactionId'],
            $threeDSecureData['CavvData'],
            $threeDSecureData['Eci'],
            $threeDSecureData['MdStatus'],
            $posAccount->getStoreKey() ?? '',
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
