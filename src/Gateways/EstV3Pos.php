<?php
/**
 * @license MIT
 */
namespace Mews\Pos\Gateways;

use Psr\Log\LogLevel;

/**
 * Class EstV3Pos
 * EstPos'un v3 hash algorithmasıyla uygulaması
 */
class EstV3Pos extends EstPos
{
    /**
     * Check 3D Hash
     *
     * @param array $data
     *
     * @return bool
     */
    public function check3DHash(array $data): bool
    {
        $actualHash = $this->requestDataMapper->create3DHash($this->account, $data, '');
        $retrievedHash = $data['HASH'];

        if ($retrievedHash === $actualHash) {
            $this->logger->log(LogLevel::DEBUG, 'hash check is successful');

            return true;
        }

        $this->logger->log(LogLevel::ERROR, 'hash check failed', [
            'data' => $data,
            'generated_hash' => $actualHash,
            'expected_hash' => $retrievedHash
        ]);

        return false;
    }
}
