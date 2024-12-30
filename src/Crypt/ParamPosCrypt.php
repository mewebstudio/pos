<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Exceptions\NotImplementedException;
use Symfony\Component\VarExporter\VarExporter;

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

        $hashParamsArr = [
            'islemGUID',
            'md',
            'mdStatus',
            'orderId',
        ];

        $hashStr = $this->buildHashString($data, $hashParamsArr, '', $posAccount->getStoreKey());

        $actualHash = $this->hashString($hashStr);

        if ($data['islemHash'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['islemHash'],
        ]);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        if (isset($requestData['Doviz_Kodu']) && '1000' !== $requestData['Doviz_Kodu']) {
            // doviz odemeler icin farkli hash
            $map = [
                $requestData['G']['CLIENT_CODE'],
                $requestData['GUID'],
                $requestData['Islem_Tutar'],
                $requestData['Toplam_Tutar'],
                $requestData['Siparis_ID'],
                $requestData['Hata_URL'] ?? '',
                $requestData['Basarili_URL'] ?? '',
            ];
        } else {
            // TRY odemeler icin hash
            $map = [
                $requestData['G']['CLIENT_CODE'],
                $requestData['GUID'],
                $requestData['Taksit'] ?? '',
                $requestData['Islem_Tutar'],
                $requestData['Toplam_Tutar'],
                $requestData['Siparis_ID'],
            ];
        }

        $hashStr = \implode(static::HASH_SEPARATOR, $map);
        $hashStr = mb_convert_encoding($hashStr, 'ISO-8859-9');

        return $this->hashString($hashStr, self::HASH_ALGORITHM);
    }
}
