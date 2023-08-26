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
    /**
     * {@inheritDoc}
     */
    public function create3DFormData(AbstractPosAccount $account, $order, string $paymentModel, string $txType, string $gatewayURL, ?AbstractCreditCard $card = null): array
    {
        $data = $this->create3DFormDataCommon($account, $order, $paymentModel, $txType, $gatewayURL, $card);

        $data['inputs']['hashAlgorithm'] = 'ver3';
        $data['inputs']['hash'] = $this->crypt->create3DHash($account, $data['inputs'], $txType);

        return $data;
    }
}
