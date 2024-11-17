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
    public function create3DHash(AbstractPosAccount $posAccount, array $requestData, ?string $txType = null): string
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
        $secondHashData = [
            $data['mdStatus'],
            $data['xid'],
            $data['amount'],
            $data['currency'],
            $posAccount->getClientId(),
            $this->createSecurityData($posAccount),
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
     * @param PosNetAccount $posAccount
     *
     * @inheritdoc
     */
    public function createHash(AbstractPosAccount $posAccount, array $requestData): string
    {
        $hashData = [
            $requestData['id'],
            $requestData['amount'],
            $requestData['currency'],
            $posAccount->getClientId(),
            $this->createSecurityData($posAccount),
        ];
        $hashStr  = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }

    /**
     * Make Security Data
     *
     * @param PosNetAccount $posAccount
     *
     * @return string
     */
    public function createSecurityData(AbstractPosAccount $posAccount): string
    {
        $hashData = [
            $posAccount->getStoreKey(),
            $posAccount->getTerminalId(),
        ];
        $hashStr  = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
