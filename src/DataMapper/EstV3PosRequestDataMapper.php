<?php
/**
 * @license MIT
 */
namespace Mews\Pos\DataMapper;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;

/**
 * Creates request data for EstPos Gateway requests that supports v3 Hash algorithm
 */
class EstV3PosRequestDataMapper extends EstPosRequestDataMapper
{
    protected const HASH_ALGORITHM = 'sha512';
    protected const HASH_SEPARATOR = '|';

    public function create3DFormData(AbstractPosAccount $account, $order, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $data = $this->create3DFormDataCommon($account, $order, $txType, $gatewayURL, $card);

        $data['inputs']['hashAlgorithm'] = 'ver3';
        unset($data['inputs']['hash']);
        $data['inputs']['hash'] = $this->create3DHash($account, $data['inputs'], $txType);

        return $data;
    }

    public function create3DHash(AbstractPosAccount $account, $order, string $txType): string
    {
        ksort($order, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($order as $key => $value){
            if (in_array(strtolower($key), ['hash', 'encoding']))  {
                unset($order[$key]);
            }
        }
        $order[] = $account->getStoreKey();
        // escape | and \ characters
        $order = str_replace("\\", "\\\\", array_values($order));
        $order = str_replace(self::HASH_SEPARATOR, "\\".self::HASH_SEPARATOR, $order);
        $hashStr = implode(self::HASH_SEPARATOR, $order);

        return $this->hashString($hashStr);
    }
}
