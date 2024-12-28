<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;

class ParamPosCrypt extends AbstractCrypt
{
    /**
     * todo remove?
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        $hashData = [
            $formInputs['clientid'],
            $formInputs['oid'],
            $formInputs['amount'],
            $formInputs['okUrl'],
            $formInputs['failUrl'],
            $formInputs['islemtipi'],
            $formInputs['taksit'],
            $formInputs['rnd'],
            $posAccount->getStoreKey(),
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $hashData);

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

        $actualHash = $this->hashFromParams($posAccount->getStoreKey(), $data, 'HASHPARAMS', ':');

        if ($data['HASH'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['HASH'],
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        $map = [
            $requestData['G']['CLIENT_CODE'],
            $requestData['GUID'],
            $requestData['Taksit'] ?? '',
            $requestData['Islem_Tutar'],
            $requestData['Toplam_Tutar'],
            $requestData['Siparis_ID'],
            $requestData['Hata_URL'] ?? '',
            $requestData['Basarili_URL'] ?? '',
        ];

        $hashStr = \implode(static::HASH_SEPARATOR, $map);
        $hashStr = mb_convert_encoding($hashStr, 'ISO-8859-9');

        return $this->hashString($hashStr, self::HASH_ALGORITHM);
    }
}
