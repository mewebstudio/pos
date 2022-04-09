<?php

namespace Mews\Pos\Factory;

use Mews\Pos\Entity\Account\EstPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Account\InterPosAccount;
use Mews\Pos\Entity\Account\PayForAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Account\VakifBankAccount;
use Mews\Pos\Exceptions\MissingAccountInfoException;
use Mews\Pos\Gateways\AbstractGateway;
use Mews\Pos\Gateways\EstPos;
use Mews\Pos\Gateways\GarantiPos;
use Mews\Pos\Gateways\InterPos;
use Mews\Pos\Gateways\PayForPos;
use Mews\Pos\Gateways\PosNet;

class AccountFactory
{
    /**
     * @param string      $bank
     * @param string      $clientId
     * @param string      $username
     * @param string      $password
     * @param string      $model
     * @param string|null $storeKey
     * @param string      $lang
     *
     * @return EstPosAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createEstPosAccount(string $bank, string $clientId, string $username, string $password, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = EstPos::LANG_TR): EstPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new EstPosAccount($bank, $model, $clientId, $username, $password, $lang, $storeKey);
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
    public static function createPayForAccount(string $bank, string $merchantId, string $userCode, string $userPassword, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = PayForPos::LANG_TR): PayForAccount
    {
        self::checkParameters($model, $merchantPass);

        return new PayForAccount($bank, $model, $merchantId, $userCode, $userPassword, $lang, $merchantPass);
    }

    /**
     * @param string      $bank
     * @param string      $clientId
     * @param string      $username
     * @param string      $password
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
    public static function createGarantiPosAccount(string $bank, string $clientId, string $username, string $password, string $terminalId, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, ?string $refundUsername = null, ?string $refundPassword = null, string $lang = GarantiPos::LANG_TR): GarantiPosAccount
    {
        self::checkParameters($model, $storeKey);

        return new GarantiPosAccount($bank, $model, $clientId, $username, $password, $lang, $terminalId, $storeKey, $refundUsername, $refundPassword);
    }


    /**
     * @param string      $bank
     * @param string      $clientId
     * @param string      $username
     * @param string      $password
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
    public static function createPosNetAccount(string $bank, string $clientId, string $username, string $password, string $terminalId, string $posNetId, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $storeKey = null, string $lang = PosNet::LANG_TR): PosNetAccount
    {
        self::checkParameters($model, $storeKey);

        return new PosNetAccount($bank, $model, $clientId, $username, $password, $lang, $terminalId, $posNetId, $storeKey);
    }

    /**
     * @param string $bank
     * @param string $clientId
     * @param string $password
     * @param string $terminalId
     * @param string $model
     * @param int    $merchantType
     * @param null   $subMerchantId
     *
     * @return VakifBankAccount
     *
     * @throws MissingAccountInfoException
     */
    public static function createVakifBankAccount(string $bank, string $clientId, string $password, string $terminalId, string $model = AbstractGateway::MODEL_NON_SECURE, int $merchantType = VakifBankAccount::MERCHANT_TYPE_STANDARD, $subMerchantId = null): VakifBankAccount
    {
        self::checkVakifBankMerchantType($merchantType, $subMerchantId);

        return new VakifBankAccount($bank, $model, $clientId, $password, $terminalId, $merchantType, $subMerchantId);
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
    public static function createInterPosAccount(string $bank, string $shopCode, string $userCode, string $userPass, string $model = AbstractGateway::MODEL_NON_SECURE, ?string $merchantPass = null, string $lang = InterPos::LANG_TR): InterPosAccount
    {
        self::checkParameters($model, $merchantPass);

        return new InterPosAccount($bank, $model, $shopCode, $userCode, $userPass, $lang, $merchantPass);
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
        if (AbstractGateway::MODEL_NON_SECURE !== $model && null === $storeKey) {
            throw new MissingAccountInfoException("$model requires storeKey!");
        }
    }

    /**
     * @param int         $merchantType
     * @param string|null $subMerchantId
     *
     * @return void
     *
     * @throws MissingAccountInfoException
     */
    private static function checkVakifBankMerchantType(int $merchantType, ?string $subMerchantId)
    {
        if (VakifBankAccount::MERCHANT_TYPE_SUB_DEALER === $merchantType && empty($subMerchantId)) {
            throw new MissingAccountInfoException('SubMerchantId is required for sub branches!');
        }
        if (!in_array($merchantType, VakifBankAccount::getMerchantTypes())) {
            throw new MissingAccountInfoException('Invalid MerchantType!');
        }
    }
}
