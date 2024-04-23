<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\AkbankPosAccount;
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
     * @param AkbankPosAccount $posAccount
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        $hashData = [
            $requestData['paymentModel'],
            $requestData['txnCode'],
            $posAccount->getClientId(),
            $posAccount->getTerminalId(),
            $requestData['orderId'],
            $requestData['lang'],
            $requestData['amount'],
            $requestData['ccbRewardAmount'] ?? '',
            $requestData['pcbRewardAmount'] ?? '',
            $requestData['xcbRewardAmount'] ?? '',
            $requestData['currencyCode'],
            $requestData['installCount'],
            $requestData['okUrl'],
            $requestData['failUrl'],
            $requestData['emailAddress'] ?? '',
            $posAccount->getSubMerchantId() ?? '',

            // 3D hosting model does not have credit card information
            $requestData['creditCard'] ?? '',
            $requestData['expiredDate'] ?? '',
            $requestData['cvv'] ?? '',

            $requestData['randomNumber'],
            $requestData['requestDateTime'],
            $requestData['b2bIdentityNumber'] ?? '',
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
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $requestData
     *
     * @return string
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
