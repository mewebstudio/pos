<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Entity\Account\AkbankPosAccount;
use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Account\ParamPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Account\ToslaPosAccount;
use Mews\Pos\Exceptions\MissingAccountInfoException;
use Mews\Pos\PosInterface;

/**
 * AccountFactory
 */
class AccountFactory
{
    /**
     * @phpstan-param PosInterface::LANG_* $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $clientId Üye iş yeri (Mağaza) numarası
     * @param non-empty-string      $kullaniciAdi
     * @param non-empty-string      $password
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     * @param non-empty-string      $lang
     *
     * @return EstPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createEstPosAccount(string $bank, string $clientId, string $kullaniciAdi, string $password, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = PosInterface::LANG_TR): EstPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new EstPosAccount($bank, $clientId, $kullaniciAdi, $password, $lang, $storeKey);
    }

    /**
     * @phpstan-param PosInterface::LANG_* $lang
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantSafeId 32 karakter üye İş Yeri numarası
     * @param non-empty-string      $terminalSafeId 32 karakter
     * @param non-empty-string      $secretKey
     * @param non-empty-string      $lang
     * @param non-empty-string|null $subMerchantId  Max 15 karakter
     *
     * @return AkbankPosAccount
     */
    public static function createAkbankPosAccount(string $bank, string $merchantSafeId, string $terminalSafeId, string $secretKey, string $lang = PosInterface::LANG_TR, ?string $subMerchantId = null): AkbankPosAccount
    {
        return new AkbankPosAccount($bank, $merchantSafeId, $terminalSafeId, $secretKey, $lang, $subMerchantId);
    }

    /**
     * @param non-empty-string $bank
     * @param non-empty-string $clientId
     * @param non-empty-string $apiUser
     * @param non-empty-string $apiPass
     *
     * @return ToslaPosAccount
     */
    public static function createToslaPosAccount(string $bank, string $clientId, string $apiUser, string $apiPass): ToslaPosAccount
    {
        return new ToslaPosAccount($bank, $clientId, $apiUser, '', PosInterface::LANG_TR, $apiPass);
    }

    /**
     * @phpstan-param PosInterface::LANG_* $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId
     * @param non-empty-string      $userCode
     * @param non-empty-string      $userPassword
     * @param non-empty-string      $model
     * @param non-empty-string|null $merchantPass
     * @param non-empty-string      $lang
     *
     * @return PayForAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayForAccount(string $bank, string $merchantId, string $userCode, string $userPassword, string $model = PosInterface::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = PosInterface::LANG_TR): PayForAccount
    {
        self::checkParameters($model, $merchantPass);

        return new PayForAccount($bank, $merchantId, $userCode, $userPassword, $lang, $merchantPass);
    }

    /**
     * @phpstan-param PosInterface::LANG_* $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId Üye işyeri Numarası
     * @param non-empty-string      $userId
     * @param non-empty-string      $password   Terminal UserID şifresi
     * @param non-empty-string      $terminalId
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     * @param non-empty-string|null $refundUsername
     * @param non-empty-string|null $refundPassword
     * @param non-empty-string      $lang
     *
     * @return GarantiPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createGarantiPosAccount(string $bank, string $merchantId, string $userId, string $password, string $terminalId, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null, ?string $refundUsername = null, ?string $refundPassword = null, string $lang = PosInterface::LANG_TR): GarantiPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new GarantiPosAccount($bank, $merchantId, $userId, $password, $lang, $terminalId, $storeKey, $refundUsername, $refundPassword);
    }


    /**
     * @phpstan-param PosInterface::LANG_* $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId Mağaza Numarası / Üye iş yeri tekil numarası
     * @param non-empty-string      $username   Yönetim panelinden oluşturulan api rollü kullanıcı adı
     * @param non-empty-string      $customerId CustomerNumber, Müşteri No
     * @param non-empty-string      $storeKey   Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param non-empty-string      $model
     * @param non-empty-string      $lang
     * @param non-empty-string|null $subMerchantId
     *
     * @return KuveytPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createKuveytPosAccount(string $bank, string $merchantId, string $username, string $customerId, string $storeKey, string $model = PosInterface::MODEL_3D_SECURE, string $lang = PosInterface::LANG_TR, ?string $subMerchantId = null): KuveytPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new KuveytPosAccount($bank, $merchantId, $username, $customerId, $storeKey, $lang, $subMerchantId);
    }

    /**
     * @phpstan-param PosInterface::LANG_*  $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId
     * @param non-empty-string      $terminalId
     * @param non-empty-string      $posNetId
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     * @param non-empty-string      $lang
     *
     * @return PosNetAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPosNetAccount(string $bank, string $merchantId, string $terminalId, string $posNetId, string $model = PosInterface::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = PosInterface::LANG_TR): PosNetAccount
    {
        self::checkParameters($model, $storeKey);

        return new PosNetAccount($bank, $merchantId, $posNetId, $terminalId, $lang, $storeKey);
    }

    /**
     * @phpstan-param PayFlexAccount::MERCHANT_TYPE_* $merchantType
     * @phpstan-param PosInterface::MODEL_*           $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId Üye işyeri numarası
     * @param non-empty-string      $password   Üye işyeri şifres
     * @param non-empty-string      $terminalNo İşlemin hangi terminal üzerinden gönderileceği bilgisi. dVB007000...
     * @param non-empty-string      $model
     * @param int                   $merchantType
     * @param non-empty-string|null $subMerchantId
     *
     * @return PayFlexAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayFlexAccount(string $bank, string $merchantId, string $password, string $terminalNo, string $model = PosInterface::MODEL_NON_SECURE, int $merchantType = PayFlexAccount::MERCHANT_TYPE_STANDARD, ?string $subMerchantId = null): PayFlexAccount
    {
        self::checkPayFlexBankMerchantType($merchantType, $subMerchantId);

        return new PayFlexAccount($bank, $merchantId, $password, $terminalNo, $merchantType, $subMerchantId);
    }

    /**
     * @phpstan-param PosInterface::LANG_*  $lang
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $shopCode
     * @param non-empty-string      $userCode
     * @param non-empty-string      $userPass
     * @param non-empty-string      $model
     * @param non-empty-string|null $merchantPass
     * @param non-empty-string      $lang
     *
     * @return InterPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createInterPosAccount(string $bank, string $shopCode, string $userCode, string $userPass, string $model = PosInterface::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = PosInterface::LANG_TR): InterPosAccount
    {
        self::checkParameters($model, $merchantPass);

        return new InterPosAccount($bank, $shopCode, $userCode, $userPass, $lang, $merchantPass);
    }

    /**
     * @param string $bank
     * @param int    $clientCode CLIENT_CODE Terminal ID
     * @param string $username   CLIENT_USERNAME Kullanıcı adı
     * @param string $password   CLIENT_PASSWORD Şifre
     * @param string $guid       GUID  Üye İşyeri ait anahtarı
     *
     * @return ParamPosAccount
     */
    public static function createParamPosAccount(string $bank, int $clientCode, string $username, string $password, string $guid): ParamPosAccount
    {
        return new ParamPosAccount($bank, $clientCode, $username, $password, $guid);
    }

    /**
     * @phpstan-param PosInterface::MODEL_* $model
     *
     * @param non-empty-string      $model
     * @param non-empty-string|null $storeKey
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkParameters(string $model, ?string $storeKey): void
    {
        if (PosInterface::MODEL_NON_SECURE === $model) {
            return;
        }

        if (null !== $storeKey) {
            return;
        }

        throw new MissingAccountInfoException(\sprintf('payment model %s requires storeKey!', $model));
    }

    /**
     * @phpstan-param PayFlexAccount::MERCHANT_TYPE_* $merchantType
     *
     * @param int                   $merchantType
     * @param non-empty-string|null $subMerchantId
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkPayFlexBankMerchantType(int $merchantType, ?string $subMerchantId): void
    {
        if (PayFlexAccount::MERCHANT_TYPE_SUB_DEALER === $merchantType && null === $subMerchantId) {
            throw new MissingAccountInfoException('SubMerchantId is required for sub branches!');
        }

        if (!\in_array($merchantType, PayFlexAccount::getMerchantTypes())) {
            throw new MissingAccountInfoException('Invalid MerchantType!');
        }
    }
}
