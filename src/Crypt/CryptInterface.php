<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;

interface CryptInterface
{
    /**
     * @param string      $str
     * @param string|null $encryptionKey
     *
     * @return string
     */
    public function hashString(string $str, ?string $encryptionKey = null): string;

    /**
     * check hash of 3D secure response
     *
     * @param AbstractPosAccount    $posAccount
     * @param array<string, string> $data
     *
     * @return bool
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool;

    /**
     * creates hash for 3D form data
     *
     * @param AbstractPosAccount    $posAccount
     * @param array<string, string> $formInputs
     *
     * @return string
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs): string;

    /**
     * create hash for API requests
     *
     * @param AbstractPosAccount   $posAccount
     * @param array<string, mixed> $requestData
     *
     * @return string
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string;

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
     * @param int<1, max> $length
     *
     * @return string
     */
    public function generateRandomString(int $length = 24): string;
}
