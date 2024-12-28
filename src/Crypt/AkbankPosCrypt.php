<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;

class AkbankPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha512';

    /**
     * returns base16 string
     * @inheritDoc
     */
    public function generateRandomString(int $length = 128): string
    {
        return parent::generateRandomString($length);
    }

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        $hashData = [
            $formInputs['paymentModel'],
            $formInputs['txnCode'],
            $formInputs['merchantSafeId'],
            $formInputs['terminalSafeId'],
            $formInputs['orderId'],
            $formInputs['lang'],
            $formInputs['amount'],
            $formInputs['ccbRewardAmount'] ?? '',
            $formInputs['pcbRewardAmount'] ?? '',
            $formInputs['xcbRewardAmount'] ?? '',
            $formInputs['currencyCode'],
            $formInputs['installCount'],
            $formInputs['okUrl'],
            $formInputs['failUrl'],
            $formInputs['emailAddress'] ?? '',
            $formInputs['subMerchantId'] ?? '',

            // 3D hosting model does not have credit card information
            $formInputs['creditCard'] ?? '',
            $formInputs['expiredDate'] ?? '',
            $formInputs['cvv'] ?? '',

            $formInputs['randomNumber'],
            $formInputs['requestDateTime'],
            $formInputs['b2bIdentityNumber'] ?? '',
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr, $posAccount->getStoreKey());
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $actualHash = $this->hashFromParams($posAccount->getStoreKey(), $data, 'hashParams', '+');

        if ($data['hash'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['hash'],
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        throw new NotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function hashString(string $str, ?string $encryptionKey = null): string
    {
        if (null === $encryptionKey) {
            throw new \LogicException('Encryption key zorunlu!');
        }

        $str = \hash_hmac(static::HASH_ALGORITHM, $str, $encryptionKey, true);

        return \base64_encode($str);
    }

    /**
     * @inheritDoc
     */
    protected function concatenateHashKey(string $hashKey, string $hashString): string
    {
        return $hashString;
    }
}
