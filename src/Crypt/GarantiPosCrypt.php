<?php

namespace Mews\Pos\Crypt;

use Mews\Pos\Entity\Account\AbstractPosAccount;
use Mews\Pos\Entity\Account\GarantiPosAccount;
use Mews\Pos\Entity\Card\AbstractCreditCard;
use Psr\Log\LogLevel;

class GarantiPosCrypt extends AbstractCrypt
{
    /**
     * @param GarantiPosAccount $account
     * {@inheritDoc}
     */
    public function create3DHash(AbstractPosAccount $account, array $requestData, ?string $txType = null): string
    {
        $map = [
            $account->getTerminalId(),
            $requestData['id'],
            $requestData['amount'],
            $requestData['success_url'],
            $requestData['fail_url'],
            $txType,
            $requestData['installment'],
            $account->getStoreKey(),
            $this->createSecurityData($account, $txType),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map));
    }

    /**
     * {@inheritdoc}
     */
    public function check3DHash(AbstractPosAccount $account, array $data): bool
    {
        $hashParams = $data['hashparams'];
        $paramsVal  = '';

        $hashParamsArr = explode(':', $hashParams);
        foreach ($hashParamsArr as $value) {
            if (isset($data[$value])) {
                $paramsVal = $paramsVal.$data[$value];
            }
        }

        $hashVal    = $paramsVal.$account->getStoreKey();
        $actualHash = $this->hashString($hashVal);

        if ($data['hash'] === $actualHash) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }
        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
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
    public function createHash(AbstractPosAccount $account, array $requestData, ?string $txType = null, ?AbstractCreditCard $card = null): string
    {
        $map = [
            $requestData['id'],
            $account->getTerminalId(),
            isset($card) ? $card->getNumber() : null,
            $requestData['amount'],
            $this->createSecurityData($account, $txType),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map));
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
        if ('void' === $txType || 'refund' === $txType) {
            $password = $account->getRefundPassword();
        } else {
            $password = $account->getPassword();
        }

        $map = [
            $password,
            str_pad($account->getTerminalId(), 9, '0', STR_PAD_LEFT),
        ];

        return $this->hashStringUpperCase(implode(static::HASH_SEPARATOR, $map));
    }

    /**
     * @param string $str
     *
     * @return string
     */
    protected function hashStringUpperCase(string $str): string
    {
        return strtoupper(hash(static::HASH_ALGORITHM, $str));
    }
}
