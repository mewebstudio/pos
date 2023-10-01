<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\PosNetAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Mews\Pos\Exceptions\NotImplementedException;
use Psr\Log\LogLevel;

class PosNetCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha256';

    /** @var string */
    protected const HASH_SEPARATOR = ';';

    /**
     * @param PosNetAccount $account
     *
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string
    {
        $secondHashData = [
            $requestData['id'],
            $requestData['amount'],
            $requestData['currency'],
            $account->getClientId(),
            $this->createSecurityData($account),
        ];
        $hashStr        = implode(static::HASH_SEPARATOR, $secondHashData);

        return $this->hashString($hashStr);
    }

    /**
     * @param PosNetAccount $account
     *
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $secondHashData = [
            $data['mdStatus'],
            $data['xid'],
            $data['amount'],
            $data['currency'],
            $account->getClientId(),
            $this->createSecurityData($account),
        ];
        $hashStr        = implode(static::HASH_SEPARATOR, $secondHashData);

        if ($this->hashString($hashStr) !== $data['mac']) {
            $this->logger->log(LogLevel::ERROR, 'hash check failed', [
                'order_id' => $data['xid'],
            ]);

            return false;
        }

        $this->logger->log(LogLevel::DEBUG, 'hash check is successful', [
            'order_id' => $data['xid'],
        ]);

        return true;
    }

    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string
    {
        throw new NotImplementedException();
    }

    /**
     * Make Security Data
     *
     * @param PosNetAccount $account
     *
     * @return string
     */
    public function createSecurityData(AbstractPosAccount $account): string
    {
        $hashData = [
            $account->getStoreKey(),
            $account->getTerminalId(),
        ];
        $hashStr  = implode(static::HASH_SEPARATOR, $hashData);

        return $this->hashString($hashStr);
    }
}
