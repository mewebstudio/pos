<?php
/**
 * @license MIT
 */

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;

class GarantiPosCrypt extends AbstractCrypt
{
    /** @var string */
    protected const HASH_ALGORITHM = 'sha512';

    /**
     * @param GarantiPosAccount $account
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData): string
    {
        $map = [
            $account->getTerminalId(),
            $requestData['orderid'],
            $requestData['txnamount'],
            $requestData['txncurrencycode'],
            $requestData['successurl'],
            $requestData['errorurl'],
            $requestData['txntype'],
            $requestData['txninstallmentcount'],
            $account->getStoreKey(),
            $this->createSecurityData($account, $requestData['txntype']),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map), self::HASH_ALGORITHM);
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        if (null === $account->getStoreKey()) {
            throw new \LogicException('Account storeKey eksik!');
        }

        $actualHash = $this->hashFromParams($account->getStoreKey(), $data, 'hashparams', ':');

        if ($data['hash'] === $actualHash) {
            $this->logger->debug('hash check is successful');

            return true;
        }

        $this->logger->error('hash check failed', [
            'data'           => $data,
            'generated_hash' => $actualHash,
            'expected_hash'  => $data['hash'],
        ]);

        return false;
    }

    /**
     * Make Hash Data
     *
     * @param GarantiPosAccount       $account
     * {@inheritDoc}
     */
    public function createHash(AbstractPosAccount $account, array $requestData): string
    {
        $map = [
            $requestData['Order']['OrderID'],
            $account->getTerminalId(),
            $requestData['Card']['Number'] ?? null,
            $requestData['Transaction']['Amount'],
            $requestData['Transaction']['CurrencyCode'],
            $this->createSecurityData($account, $requestData['Transaction']['Type']),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map), self::HASH_ALGORITHM);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function hashString(string $str): string
    {
        return $this->hashStringUpperCase($str, self::HASH_ALGORITHM);
    }

    /**
     * Make Security Data
     *
     * @param GarantiPosAccount $account
     * @param string|null       $txType
     *
     * @return string
     */
    private function createSecurityData(AbstractPosAccount $account, ?string $txType = null): string
    {
        $password = 'void' === $txType || 'refund' === $txType ? $account->getRefundPassword() : $account->getPassword();

        $map = [
            $password,
            str_pad($account->getTerminalId(), 9, '0', STR_PAD_LEFT),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map), 'sha1');
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function hashStringUpperCase(string $str, string $algorithm): string
    {
        return strtoupper(hash($algorithm, $str));
    }
}
