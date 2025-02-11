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
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string
    {
        throw new NotImplementedException();
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (isset($data['TURKPOS_RETVAL_Hash'])) {
            return $this->check3DPayOr3DHostHash($posAccount, $data);
        }

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
        $requestData2 = $requestData['TP_WMD_UCD']
            ?? $requestData['TP_Islem_Odeme_WD']
            ?? $requestData['Pos_Odeme']
            ?? $requestData['TP_Islem_Odeme_OnProv_WMD'];

        if (isset($requestData['TP_Islem_Odeme_WD']) || isset($requestData['TP_Islem_Odeme_OnProv_WMD'])) {
            // doviz ve on provizyon odemeler icin farkli hash
            $map = [
                $requestData2['G']['CLIENT_CODE'],
                $requestData2['GUID'],
                $requestData2['Islem_Tutar'],
                $requestData2['Toplam_Tutar'],
                $requestData2['Siparis_ID'],
                $requestData2['Hata_URL'],
                $requestData2['Basarili_URL'],
            ];
        } elseif (isset($requestData['Pos_Odeme'])) {
            // 3d pay odeme icin hash
            $map = [
                $requestData2['G']['CLIENT_CODE'],
                $requestData2['GUID'],
                $requestData2['Taksit'],
                $requestData2['Islem_Tutar'],
                $requestData2['Toplam_Tutar'],
                $requestData2['Siparis_ID'],
                $requestData2['Hata_URL'],
                $requestData2['Basarili_URL'],
            ];
        } else {
            // TRY odemeler icin hash
            $map = [
                $requestData2['G']['CLIENT_CODE'],
                $requestData2['GUID'],
                $requestData2['Taksit'],
                $requestData2['Islem_Tutar'],
                $requestData2['Toplam_Tutar'],
                $requestData2['Siparis_ID'],
            ];
        }

        $hashStr = \implode(static::HASH_SEPARATOR, $map);
        $hashStr = \mb_convert_encoding($hashStr, 'ISO-8859-9');

        return $this->hashString($hashStr, self::HASH_ALGORITHM);
    }

    /**
     * check hash of 3D Pay and 3d Host response
     *
     * @param AbstractPosAccount    $posAccount
     * @param array<string, string> $data
     *
     * @return bool
     */
    private function check3DPayOr3DHostHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $hashParamsArr = [
            'TURKPOS_RETVAL_GUID',
            'TURKPOS_RETVAL_Dekont_ID',
            'TURKPOS_RETVAL_Tahsilat_Tutari',
            'TURKPOS_RETVAL_Siparis_ID',
            'TURKPOS_RETVAL_Islem_ID',
        ];

        $hashStr = $this->buildHashString($data, $hashParamsArr, '');
        $hashStr = $posAccount->getClientId().$hashStr;

        $actualHash = $this->hashString($hashStr);

        if ($data['TURKPOS_RETVAL_Hash'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['TURKPOS_RETVAL_Hash'],
        ]);

        return false;
    }
}
