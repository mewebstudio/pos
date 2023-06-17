<?php

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Gateways\AbstractGateway;
use Psr\Log\LogLevel;

class PosNetV1PosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = '';

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string
    {
        if ($account->getModel() === AbstractGateway::MODEL_3D_SECURE || $account->getModel() === AbstractGateway::MODEL_3D_PAY) {
            $hashData = [
                $account->getClientId(),
                $account->getTerminalId(),
                $requestData['CardNo'],
                $requestData['Cvv'],
                $requestData['ExpiredDate'],
                $requestData['Amount'],
                $account->getStoreKey(),
            ];
            $hashStr  = implode(static::HASH_SEPARATOR, $hashData);
            return $this->hashString($hashStr);
        }

        return '';
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $hashStr = '';

        if ($account->getModel() === AbstractGateway::MODEL_3D_SECURE || $account->getModel() === AbstractGateway::MODEL_3D_PAY) {
            $hashData = [
                $data['ECI'],
                $data['CAVV'],
                $data['MdStatus'],
                $data['MdErrorMessage'],
                $data['MD'],
                $data['SecureTransactionId'],
                $account->getStoreKey(),
            ];
            $hashStr        = implode(static::HASH_SEPARATOR, $hashData);
        }

        if ($this->hashString($hashStr) !== $data['Mac']) {
            $this->logger->log(LogLevel::ERROR, 'hash check failed', [
                'order_id' => $data['OrderId'],
            ]);

            return false;
        }

        $this->logger->log(LogLevel::DEBUG, 'hash check is successful', [
            'order_id' => $data['OrderId'],
        ]);

        return true;
    }

    /**
     * @param PosNetAccount $account
     *
     * @inheritDoc
     */
    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string
    {
        $hashStr = '';

        if (isset($requestData['ThreeDSecureData'])
            && ($account->getModel() === AbstractGateway::MODEL_3D_SECURE || $account->getModel() === AbstractGateway::MODEL_3D_PAY)) {
            $hashData = [
                $account->getClientId(),
                $account->getTerminalId(),
                $requestData['ThreeDSecureData']['SecureTransactionId'],
                $requestData['ThreeDSecureData']['CavvData'],
                $requestData['ThreeDSecureData']['Eci'],
                $requestData['ThreeDSecureData']['MdStatus'],
                $account->getStoreKey(),
            ];

            $hashStr = implode(static::HASH_SEPARATOR, $hashData);
        }

        return $this->hashString($hashStr);
    }
}
