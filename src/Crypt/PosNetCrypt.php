<?php

/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Exceptions\NotImplementedException;

class PosNetCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = ';';

    /**
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $posAccount, array $formInputs, ?string $txType = null): string
    {
        throw new NotImplementedException();
    }

    /**
     * @param PosNetAccount $posAccount
     *
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $posAccount, array $data): bool
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $secondHashData = [
            $data['mdStatus'],
            $data['xid'],
            $data['amount'],
            $data['currency'],
            $posAccount->getClientId(),
            $this->createSecurityData($posAccount->getStoreKey(), $posAccount->getTerminalId()),
        ];
        $hashStr        = implode(static::HASH_SEPARATOR, $secondHashData);

        if ($this->hashString($hashStr) !== $data['mac']) {
            $this->logger->error('hash check failed', [
                'order_id' => $data['xid'],
            ]);

            return false;
        }

        $this->logger->debug('hash check is successful', [
            'order_id' => $data['xid'],
        ]);

        return true;
    }

    /**
     * @param array{amount: int, currency: string, id: string} $order
     *
     * @inheritdoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData, array $order = []): string
    {
        if (null === $posAccount->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $hashData = [
            $order['id'],
            $order['amount'],
            $order['currency'],
            $requestData['mid'],
            $this->createSecurityData($posAccount->getStoreKey(), $requestData['tid']),
        ];
        $hashStr  = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * Make Security Data
     *
     * @param string $storeKey
     * @param string $terminalId
     *
     * @return string
     */
    private function createSecurityData(string $storeKey, string $terminalId): string
    {
        $hashData = [
            $storeKey,
            $terminalId,
        ];
        $hashStr  = \implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
