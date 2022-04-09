<?php

namespace Mews\Pos\Gateways;

/**
 * Class PosNetCrypt
 */
class PosNetCrypt
{
    /**
     * @var string
     */
    private $algo;

    /**
     * @var int
     */
    private $ks;

    /**
     *
     * @access private
     */
    private $error;

    /**
     * PosNetCrypt constructor.
     */
    public function __construct()
    {
        srand((float) microtime() * 10000000);
        $this->algo = 'des-ede3-cbc';
        $this->ks = 24;
        $this->error = '';
    }

    /**
     * This function is used to get encryption errors.
     *
     * @return string
     */
    public function getLastError(): string
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function createIV()
    {
        $temp = sprintf("%05d", rand());
        $temp .= sprintf("%05d", rand());
        $temp .= sprintf("%05d", rand());
        $temp .= sprintf("%05d", rand());

        return pack("H*", substr($temp, 0, 16));
    }

    /**
     * @param $data
     * @param $key
     *
     * @return string
     */
    public function encrypt($data, $key): string
    {
        // Create IV
        $iv = $this->createIV();

        // Encrypt Data
        $encryptedData = openssl_encrypt($data, $this->algo, $this->detKey($key), OPENSSL_RAW_DATA, $iv);

        // Add IV and Convert to HEX
        $hexEncryptedData = strtoupper(bin2hex($iv)).strtoupper(bin2hex($encryptedData));

        // Add CRC
        return $this->addCrc($hexEncryptedData);
    }

    /**
     * @param $data
     * @param $key
     *
     * @return bool|string
     */
    public function decrypt($data, $key)
    {

        $parsedData = $this->parseEncryptedData($data);

        if (!$parsedData) {
            return false;
        }

        // Check CRC
        if (!$this->checkCrc($parsedData['crc_data'], $parsedData['crc'])) {
            $this->error = "CRC is not valid! (".$parsedData['crc'].")";

            return false;
        }

        // Get IV
        $iv = pack("H*", $parsedData['iv']);

        // Get Encrypted Data
        $encryptedData = pack("H*", $parsedData['payload']);

        // Decrypt Data
        return openssl_decrypt($encryptedData, $this->algo, $this->detKey($key), OPENSSL_RAW_DATA, $iv);
    }

    /**
     * @param $key
     *
     * @return bool|string
     */
    public function detKey($key)
    {
        return substr(strtoupper(md5($key)), 0, $this->ks);
    }

    /**
     * @param $data
     *
     * @return string
     */
    public function addCrc($data): string
    {
        $crc = crc32($data);
        $hexCrc = sprintf("%08x", $crc);
        $data .= strtoupper($hexCrc);

        return $data;
    }

    /**
     * @param $data
     * @param $crc
     *
     * @return bool
     */
    public function checkCrc($data, $crc): bool
    {
        $crcCalc = crc32($data);
        $hexCrc = sprintf("%08x", $crcCalc);
        $crcCalc = strtoupper($hexCrc);

        return strcmp($crcCalc, $crc) === 0;
    }

    /**
     * @param string $data
     *
     * @return array|bool
     */
    private function parseEncryptedData(string $data)
    {

        if (strlen($data) < 16 + 8) {
            return false;
        }

        return [
            'crc' => substr($data, -8),
            'crc_data' => substr($data, 0, strlen($data)-8),
            'iv' => substr($data, 0, 16),
            'payload' => substr($data, 16, strlen($data)-16-8),
        ];
    }
}
