<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Factory;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Account\KuveytPosAccount;
use Mews\Pos\Entity\Account\PayFlexAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Exceptions\MissingAccountInfoException;
use Mews\Pos\Gateways\AbstractGateway;

/**
 * AccountFactory
 */
class AccountFactory
{
    /**
     * @param string      $bank
     * @param string      $clientId     Üye iş yeri numarası
     * @param string      $kullaniciAdi
     * @param string      $password
     * @param string      $model
     * @param string|null $storeKey
     * @param string      $lang
     *
     * @return EstPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createEstPosAccount(string $bank, string $clientId, string $kullaniciAdi, string $password, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = AbstractGateway::LANG_TR): EstPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new EstPosAccount($bank, $clientId, $kullaniciAdi, $password, $lang, $storeKey);
    }

    /**
     * @param string      $bank
     * @param string      $merchantId
     * @param string      $userCode
     * @param string      $userPassword
     * @param string      $model
     * @param string|null $merchantPass
     * @param string      $lang
     *
     * @return PayForAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayForAccount(string $bank, string $merchantId, string $userCode, string $userPassword, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = AbstractGateway::LANG_TR): PayForAccount
    {
        self::checkParameters($model, $merchantPass);

        return new PayForAccount($bank, $merchantId, $userCode, $userPassword, $lang, $merchantPass);
    }

    /**
     * @param string      $bank
     * @param string      $merchantId     Üye işyeri Numarası
     * @param string      $userId
     * @param string      $password       Terminal UserID şifresi
     * @param string      $terminalId
     * @param string      $model
     * @param string|null $storeKey
     * @param string|null $refundUsername
     * @param string|null $refundPassword
     * @param string      $lang
     *
     * @return GarantiPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createGarantiPosAccount(string $bank, string $merchantId, string $userId, string $password, string $terminalId, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, ?string $refundUsername = null, ?string $refundPassword = null, string $lang = AbstractGateway::LANG_TR): GarantiPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new GarantiPosAccount($bank, $merchantId, $userId, $password, $lang, $terminalId, $storeKey, $refundUsername, $refundPassword);
    }


    /**
     * @param string      $bank
     * @param string      $merchantId    Mağaza Numarası
     * @param string      $username      POS panelinizden kullanıcı işlemleri sayfasında APİ rolünde kullanıcı oluşturulmalıdır
     * @param string      $customerId    CustomerNumber, Müşteri No
     * @param string      $storeKey      Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param string      $model
     * @param string      $lang
     * @param string|null $subMerchantId
     *
     * @return KuveytPosAccount
     */
    public static function createKuveytPosAccount(string $bank, string $merchantId, string $username, string $customerId, string $storeKey, string $model = AbstractGateway::MODEL_3D_SECURE, string $lang = AbstractGateway::LANG_TR, ?string $subMerchantId = null): KuveytPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new KuveytPosAccount($bank, $merchantId, $username, $customerId, $storeKey, $lang, $subMerchantId);
    }

    /**
     * @param string      $bank
     * @param string      $merchantId
     * @param string      $terminalId
     * @param string      $posNetId
     * @param string      $model
     * @param string|null $storeKey
     * @param string      $lang
     *
     * @return PosNetAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPosNetAccount(string $bank, string $merchantId, string $terminalId, string $posNetId, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = AbstractGateway::LANG_TR): PosNetAccount
    {
        self::checkParameters($model, $storeKey);

        return new PosNetAccount($bank, $merchantId, $posNetId, $terminalId, $lang, $storeKey);
    }

    /**
     * @param string                          $bank
     * @param string                          $merchantId Üye işyeri numarası
     * @param string                          $password   Üye işyeri şifres
     * @param string                          $terminalNo İşlemin hangi terminal üzerinden gönderileceği bilgisi.
     *                                                    VB007000...
     * @param string                          $model
     * @param PayFlexAccount::MERCHANT_TYPE_* $merchantType
     * @param null                            $subMerchantId
     *
     * @return PayFlexAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayFlexAccount(string $bank, string $merchantId, string $password, string $terminalNo, string $model = AbstractGateway::MODEL_NON_SECURE, int $merchantType = PayFlexAccount::MERCHANT_TYPE_STANDARD, $subMerchantId = null): PayFlexAccount
    {
        self::checkPayFlexBankMerchantType($merchantType, $subMerchantId);

        return new PayFlexAccount($bank, $merchantId, $password, $terminalNo, $merchantType, $subMerchantId);
    }

    /**
     * @param string      $bank
     * @param string      $shopCode
     * @param string      $userCode
     * @param string      $userPass
     * @param string      $model
     * @param string|null $merchantPass
     * @param string      $lang
     *
     * @return InterPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createInterPosAccount(string $bank, string $shopCode, string $userCode, string $userPass, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = AbstractGateway::LANG_TR): InterPosAccount
    {
        self::checkParameters($model, $merchantPass);

        return new InterPosAccount($bank, $shopCode, $userCode, $userPass, $lang, $merchantPass);
    }

    /**
     * @param string      $model
     * @param string|null $storeKey
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkParameters(string $model, ?string $storeKey)
    {
        if (AbstractGateway::MODEL_NON_SECURE === $model) {
            return;
        }

        if (null !== $storeKey) {
            return;
        }

        throw new MissingAccountInfoException(sprintf('payment model %s requires storeKey!', $model));
    }

    /**
     * @param int         $merchantType
     * @param string|null $subMerchantId
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkPayFlexBankMerchantType(int $merchantType, ?string $subMerchantId)
    {
        if (PayFlexAccount::MERCHANT_TYPE_SUB_DEALER === $merchantType && empty($subMerchantId)) {
            throw new MissingAccountInfoException('SubMerchantId is required for sub branches!');
        }

        if (!in_array($merchantType, PayFlexAccount::getMerchantTypes())) {
            throw new MissingAccountInfoException('Invalid MerchantType!');
        }
    }
}
