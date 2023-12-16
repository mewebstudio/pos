<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

interface CryptInterface
{
    /**
     * check hash of 3D secure response
     *
     * @param AbstractPosAccount    $account
     * @param array<string, string> $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool;

    /**
     * creates hash for 3D secure payments
     *
     * @param AbstractPosAccount    $account
     * @param array<string, string> $requestData
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string;

    /**
     * create hash for non-3D actions
     *
     * @param AbstractPosAccount   $account
     * @param array<string, mixed> $requestData
     *
     * @return string
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string;

    /**
     * @param string               $storeKey       hashing key
     * @param array<string, mixed> $data           array that contains values for the params specified in $hashParams
     * @param string               $hashParamsKey  key name whose value $data that contains hashParamNames separated by
     *                                             $paramSeparator
     * @param non-empty-string     $paramSeparator [:;]
     *
     * @return string hashed string from values of $hashParams
     */
    public function hashFromParams(string $storeKey, array $data, string $hashParamsKey, string $paramSeparator): string;


    /**
     * generates random string for using as a nonce in requests
     *
     * @param int $length
     *
     * @return string
     */
    public function generateRandomString(int $length = 24): string;
}
