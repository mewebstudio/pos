<?php

namespace Mews\Pos;

/**
 * Class PosNetCrypt
 * @package Mews\Pos
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
     * @var int
     */
    private $block;

    /**
     *
     * @access private
     */
    private $error;

    /**
     * PosNetCrypt constructor.
     */
    public function __construct ()
    {
        srand((double) microtime() * 10000000);
        $this->algo = 'des-ede3-cbc';
        $this->block = 8;
        $this->ks = 24;
        $this->error = '';
    }

    /**
     * This function is used to get encryption errors.
     *
     * @return string
     */
    public function getLastError ()
    {
        return $this->error;
    }

    /**
     * @return string
     */
    public function createIV ()
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
     * @return string
     */
    public function encrypt($data, $key)
    {
        // Create IV
        $iv = $this->createIV();

        // Encrypt Data
        $encrypted_data = openssl_encrypt($data, $this->algo, $this->detKey($key), OPENSSL_RAW_DATA, $iv);

        // Add IV and Convert to HEX
        $hex_encrypted_data = strtoupper(bin2hex($iv)).strtoupper(bin2hex($encrypted_data));

        // Add CRC
        $hex_encrypted_data = $this->addCrc($hex_encrypted_data);

        return $hex_encrypted_data;
    }

    /**
     * @param $data
     * @param $key
     * @return bool|string
     */
    public function decrypt($data, $key) {

        $parsed_data = $this->parseEncryptedData($data);

        if (!$parsed_data) return false;

        // Check CRC
        if (!$this->checkCrc($parsed_data['crc_data'], $parsed_data['crc'])) {
            $this->error = "CRC is not valid! (" . $parsed_data['crc'] . ")";
            return FALSE;
        }

        // Get IV
        $iv = pack("H*", $parsed_data['iv']);

        // Get Encrypted Data
        $encrypted_data = pack("H*", $parsed_data['payload']);

        // Decrypt Data
        $decrypted_data = openssl_decrypt($encrypted_data, $this->algo, $this->detKey($key), OPENSSL_RAW_DATA, $iv);

        return $decrypted_data;
    }

    /**
     * @param $key
     * @return bool|string
     */
    public function detKey($key)
    {
        $deskey = substr(strtoupper(md5($key)), 0, $this->ks);
        return $deskey;
    }

    /**
     * @param $data
     * @return string
     */
    public function addCrc($data)
    {
        $crc = crc32($data);
        $hex_crc = sprintf("%08x", $crc);
        $data .= strtoupper($hex_crc);

        return $data;
    }

    /**
     * @param $data
     * @param $crc
     * @return bool
     */
    public function checkCrc($data, $crc)
    {
        $crc_calc = crc32($data);
        $hex_crc = sprintf("%08x", $crc_calc);
        $crc_calc = strtoupper($hex_crc);

        return strcmp($crc_calc, $crc) == 0 ? true : false;
    }

    /**
     * @param string $data
     * @return array|bool
     */
    private function parseEncryptedData(string $data){

        if (strlen($data) < 16 + 8) return false;

        return [
            'crc' => substr($data, -8),
            'crc_data' => substr($data, 0, strlen($data)-8),
            'iv' => substr($data, 0, 16),
            'payload' => substr($data, 16, strlen($data)-16-8)
        ];
    }
}
