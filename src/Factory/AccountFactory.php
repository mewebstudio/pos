<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Factory;

use Mews\Pos\Exception\MissingAccountInfoException;
use Mews\Pos\Gateway\AkbankPos;
use Mews\Pos\Gateway\AssecoPos;
use Mews\Pos\Gateway\GarantiPos;
use Mews\Pos\Gateway\InterPos;
use Mews\Pos\Gateway\IyzicoPos;
use Mews\Pos\Gateway\KuveytPos;
use Mews\Pos\Gateway\Param3DHostPos;
use Mews\Pos\Gateway\ParamPos;
use Mews\Pos\Gateway\PayFlexCPV4Pos;
use Mews\Pos\Gateway\PayFlexV4Pos;
use Mews\Pos\Gateway\PayForPos;
use Mews\Pos\Gateway\PayTrPos;
use Mews\Pos\Gateway\PosNetPos;
use Mews\Pos\Gateway\PosNetV1Pos;
use Mews\Pos\Gateway\ToslaPos;
use Mews\Pos\Gateway\VakifKatilimPos;
use Mews\Pos\Model\Account\AbstractPosAccount;
use Mews\Pos\Model\Account\AkbankPosAccount;
use Mews\Pos\Model\Account\AssecoPosAccount;
use Mews\Pos\Model\Account\BoaPosAccount;
use Mews\Pos\Model\Account\GarantiPosAccount;
use Mews\Pos\Model\Account\InterPosAccount;
use Mews\Pos\Model\Account\IyzicoPosAccount;
use Mews\Pos\Model\Account\ParamPosAccount;
use Mews\Pos\Model\Account\PayFlexPosAccount;
use Mews\Pos\Model\Account\PayForPosAccount;
use Mews\Pos\Model\Account\PayTrPosAccount;
use Mews\Pos\Model\Account\PosNetPosAccount;
use Mews\Pos\Model\Account\ToslaPosAccount;
use Mews\Pos\PosInterface;

/**
 * AccountFactory
 */
class AccountFactory
{
    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $clientId     Üye iş yeri (Mağaza) numarası
     * @param non-empty-string      $kullaniciAdi
     * @param non-empty-string      $password
     * @param non-empty-string|null $secretKey
     *
     * @return AssecoPosAccount
     */
    public static function createAssecoPosAccount(string $bank, string $clientId, string $kullaniciAdi, string $password, ?string $secretKey = null): AssecoPosAccount
    {
        return new AssecoPosAccount($bank, $clientId, $kullaniciAdi, $password, $secretKey);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantSafeId 32 karakter üye İş Yeri numarası
     * @param non-empty-string      $terminalSafeId 32 karakter
     * @param non-empty-string      $secretKey
     * @param non-empty-string|null $subMerchantId  Max 15 karakter
     *
     * @return AkbankPosAccount
     */
    public static function createAkbankPosAccount(string $bank, string $merchantSafeId, string $terminalSafeId, string $secretKey, ?string $subMerchantId = null): AkbankPosAccount
    {
        return new AkbankPosAccount($bank, $merchantSafeId, $terminalSafeId, $secretKey, $subMerchantId);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $apiKey
     * @param non-empty-string      $secretKey
     * @param non-empty-string|null $subMerchantKey
     *
     * @return IyzicoPosAccount
     */
    public static function createIyzicoPosAccount(string $bank, string $apiKey, string $secretKey, ?string $subMerchantKey = null): IyzicoPosAccount
    {
        return new IyzicoPosAccount($bank, $apiKey, $secretKey, $subMerchantKey);
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
        return new ToslaPosAccount($bank, $clientId, $apiUser, '', $apiPass);
    }

    /**
     * @param non-empty-string $bank         Config key (e.g. 'paytr')
     * @param non-empty-string $merchantId   merchant_id from the PayTR panel
     * @param non-empty-string $merchantSalt merchant_salt from the PayTR panel (appended to hash string)
     * @param non-empty-string $merchantKey  merchant_key from the PayTR panel (HMAC signing key)
     *
     * @return PayTrPosAccount
     */
    public static function createPayTrPosAccount(string $bank, string $merchantId, string $merchantSalt, string $merchantKey): PayTrPosAccount
    {
        return new PayTrPosAccount($bank, $merchantId, $merchantSalt, $merchantKey);
    }

    /**
     * @phpstan-param PayForPosAccount::MBR_ID_* $mbrId
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId   Üye işyeri numarası.
     * @param non-empty-string      $userCode     Otorizasyon sistemi kullanıcı kodu.
     * @param non-empty-string      $userPassword Otorizasyon sistemi kullanıcı şifresi.
     * @param non-empty-string|null $merchantPass 3D Secure şifresidir.
     * @param non-empty-string      $mbrId        Kurum kodudur.
     *
     * @return PayForPosAccount
     */
    public static function createPayForPosAccount(
        string  $bank,
        string  $merchantId,
        string  $userCode,
        string  $userPassword,
        ?string $merchantPass = null,
        string  $mbrId = PayForPosAccount::MBR_ID_FINANSBANK
    ): PayForPosAccount {
        return new PayForPosAccount(
            $bank,
            $merchantId,
            $userCode,
            $userPassword,
            $merchantPass,
            $mbrId
        );
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId     Üye işyeri Numarası
     * @param non-empty-string      $userId
     * @param non-empty-string      $password       Terminal UserID şifresi
     * @param non-empty-string      $terminalId
     * @param non-empty-string|null $secretKey
     * @param non-empty-string|null $refundUsername
     * @param non-empty-string|null $refundPassword
     *
     * @return GarantiPosAccount
     */
    public static function createGarantiPosAccount(string $bank, string $merchantId, string $userId, string $password, string $terminalId, ?string $secretKey = null, ?string $refundUsername = null, ?string $refundPassword = null): GarantiPosAccount
    {
        return new GarantiPosAccount($bank, $merchantId, $userId, $password, $terminalId, $secretKey, $refundUsername, $refundPassword);
    }


    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId    Mağaza Numarası / Üye iş yeri tekil numarası
     * @param non-empty-string      $username      Yönetim panelinden oluşturulan api rollü kullanıcı adı
     * @param non-empty-string      $customerId    CustomerNumber, Müşteri No
     * @param non-empty-string      $secretKey     Oluşturulan APİ kullanıcısının şifre bilgisidir.
     * @param non-empty-string|null $subMerchantId
     *
     * @return BoaPosAccount
     */
    public static function createBoaPosAccount(string $bank, string $merchantId, string $username, string $customerId, string $secretKey, ?string $subMerchantId = null): BoaPosAccount
    {
        return new BoaPosAccount($bank, $merchantId, $username, $customerId, $secretKey, $subMerchantId);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId
     * @param non-empty-string      $terminalId
     * @param non-empty-string      $posNetId
     * @param non-empty-string|null $secretKey
     *
     * @return PosNetPosAccount
     */
    public static function createPosNetPosAccount(string $bank, string $merchantId, string $terminalId, string $posNetId, ?string $secretKey = null): PosNetPosAccount
    {
        return new PosNetPosAccount($bank, $merchantId, $posNetId, $terminalId, $secretKey);
    }

    /**
     * @phpstan-param PayFlexPosAccount::MERCHANT_TYPE_* $merchantType
     *
     * @param non-empty-string      $bank
     * @param non-empty-string      $merchantId    Üye işyeri numarası
     * @param non-empty-string      $password      Üye işyeri şifres
     * @param non-empty-string      $terminalNo    İşlemin hangi terminal üzerinden gönderileceği bilgisi. dVB007000...
     * @param int                   $merchantType
     * @param non-empty-string|null $subMerchantId
     *
     * @return PayFlexPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createPayFlexPosAccount(string $bank, string $merchantId, string $password, string $terminalNo, int $merchantType = PayFlexPosAccount::MERCHANT_TYPE_STANDARD, ?string $subMerchantId = null): PayFlexPosAccount
    {
        self::checkPayFlexBankMerchantType($merchantType, $subMerchantId);

        return new PayFlexPosAccount($bank, $merchantId, $password, $terminalNo, $merchantType, $subMerchantId);
    }

    /**
     * @param non-empty-string      $bank
     * @param non-empty-string      $shopCode
     * @param non-empty-string      $userCode
     * @param non-empty-string      $userPass
     * @param non-empty-string|null $merchantPass
     *
     * @return InterPosAccount
     */
    public static function createInterPosAccount(string $bank, string $shopCode, string $userCode, string $userPass, ?string $merchantPass = null): InterPosAccount
    {
        return new InterPosAccount($bank, $shopCode, $userCode, $userPass, $merchantPass);
    }

    /**
     * @param string      $bank
     * @param string      $clientCode CLIENT_CODE
     * @param string      $username   CLIENT_USERNAME Kullanıcı adı
     * @param string      $password   CLIENT_PASSWORD Şifre
     * @param string      $guid       GUID  Üye İşyeri ait anahtarı
     * @param string|null $terminalId Terminal_ID
     *
     * @return ParamPosAccount
     */
    public static function createParamPosAccount(string $bank, string $clientCode, string $username, string $password, string $guid, ?string $terminalId = null): ParamPosAccount
    {
        return new ParamPosAccount($bank, $clientCode, $username, $password, $guid, $terminalId);
    }

    /**
     * Creates an account from a gateway class name and a flat credentials array.
     * Intended for configuration-driven callers (framework wrappers, config files).
     *
     * Credential keys per gateway ([] = optional, parentheses = bank's own field name):
     * - AssecoPos:       merchant_id (ClientId), user_name (KullaniciAdi), user_password (KullaniciSifresi), [secret_key (StoreKey)]
     * - AkbankPos:       merchant_id (MerchantSafeId), terminal_id (TerminalSafeId), secret_key (SecretKey), [sub_merchant_id]
     * - GarantiPos:      merchant_id, user_name (ProvUserID), user_password (ProvisionPassword), terminal_id, [secret_key (StoreKey)], [refund_user_name (ProvUserID)], [refund_user_password (ProvisionPassword)]
     * - InterPos:        merchant_id (ShopCode), user_name (UserCode), user_password (UserPass), [secret_key (MerchantPass)]
     * - IyzicoPos:       merchant_id (ApiKey), secret_key (SecretKey), [sub_merchant_id (SubMerchantKey)]
     * - KuveytPos:       merchant_id (MerchantId), user_name (UserName), terminal_id (CustomerId/MüşteriNo), secret_key (Password), [sub_merchant_id]
     * - Param3DHostPos:  merchant_id (CLIENT_CODE), user_name (CLIENT_USERNAME), user_password (CLIENT_PASSWORD), secret_key (GUID), [terminal_id (Terminal_ID)]
     * - ParamPos:        merchant_id (CLIENT_CODE), user_name (CLIENT_USERNAME), user_password (CLIENT_PASSWORD), secret_key (GUID), [terminal_id (Terminal_ID)]
     * - PayFlexCPV4Pos:  merchant_id, user_password (Password), terminal_id (TerminalNo), [merchant_type], [sub_merchant_id]
     * - PayFlexV4Pos:    merchant_id, user_password (Password), terminal_id (TerminalNo), [merchant_type], [sub_merchant_id]
     * - PayForPos:       merchant_id, user_name (UserCode), user_password (UserPass), [secret_key (MerchantPass)], [mbr_id]
     * - PayTrPos:        merchant_id, user_password (MerchantSalt), secret_key (MerchantKey)
     * - PosNetPos:       merchant_id, terminal_id, user_name (PosNetId), [secret_key (EncKey)]
     * - PosNetV1Pos:     merchant_id, terminal_id, user_name (PosNetId), [secret_key (EncKey)]
     * - ToslaPos:        merchant_id (ClientId), user_name (ApiUser), secret_key (ApiPass)
     * - VakifKatilimPos: merchant_id (MerchantId), user_name (UserName), terminal_id (CustomerId/MüşteriNo), secret_key (Password), [sub_merchant_id]
     *
     * @param class-string<PosInterface>                $gatewayClass
     * @param non-empty-string                          $bank
     * @param array<non-empty-string, non-empty-string> $credentials
     *
     * @return AbstractPosAccount
     *
     * @throws \DomainException            if no account matches the given gateway class
     * @throws MissingAccountInfoException propagated from PayFlex validation
     */
    public static function createForGateway(string $gatewayClass, string $bank, array $credentials): AbstractPosAccount
    {
        return match ($gatewayClass) {
            AssecoPos::class => self::createAssecoPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['user_password'],
                $credentials['secret_key'] ?? null,
            ),
            AkbankPos::class => self::createAkbankPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['terminal_id'],
                $credentials['secret_key'],
                $credentials['sub_merchant_id'] ?? null,
            ),
            GarantiPos::class => self::createGarantiPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['user_password'],
                $credentials['terminal_id'],
                $credentials['secret_key'] ?? null,
                $credentials['refund_user_name'] ?? null,
                $credentials['refund_user_password'] ?? null,
            ),
            InterPos::class => self::createInterPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['user_password'],
                $credentials['secret_key'] ?? null,
            ),
            IyzicoPos::class => self::createIyzicoPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['secret_key'],
                $credentials['sub_merchant_id'] ?? null,
            ),
            KuveytPos::class, VakifKatilimPos::class => self::createBoaPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['terminal_id'],
                $credentials['secret_key'],
                $credentials['sub_merchant_id'] ?? null,
            ),
            Param3DHostPos::class, ParamPos::class => self::createParamPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['user_password'],
                $credentials['secret_key'],
                $credentials['terminal_id'] ?? null,
            ),
            PayFlexCPV4Pos::class, PayFlexV4Pos::class => self::createPayFlexPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_password'],
                $credentials['terminal_id'],
                isset($credentials['merchant_type']) ? (int) $credentials['merchant_type'] : PayFlexPosAccount::MERCHANT_TYPE_STANDARD, // @phpstan-ignore argument.type
                $credentials['sub_merchant_id'] ?? null,
            ),
            PayForPos::class => self::createPayForPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['user_password'],
                $credentials['secret_key'] ?? null,
                $credentials['mbr_id'] ?? PayForPosAccount::MBR_ID_FINANSBANK, // @phpstan-ignore argument.type
            ),
            PayTrPos::class => self::createPayTrPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_password'],
                $credentials['secret_key'],
            ),
            PosNetPos::class, PosNetV1Pos::class => self::createPosNetPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['terminal_id'],
                $credentials['user_name'],
                $credentials['secret_key'] ?? null,
            ),
            ToslaPos::class => self::createToslaPosAccount(
                $bank,
                $credentials['merchant_id'],
                $credentials['user_name'],
                $credentials['secret_key'],
            ),
            default => throw new \DomainException(\sprintf('No matching Account for gateway %s', $gatewayClass)),
        };
    }

    /**
     * @phpstan-param PayFlexPosAccount::MERCHANT_TYPE_* $merchantType
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
        if (PayFlexPosAccount::MERCHANT_TYPE_SUB_DEALER === $merchantType && null === $subMerchantId) {
            throw new MissingAccountInfoException('SubMerchantId is required for sub branches!');
        }

        if (!\in_array($merchantType, PayFlexPosAccount::getMerchantTypes())) {
            throw new MissingAccountInfoException('Invalid MerchantType!');
        }
    }
}
