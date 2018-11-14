<?php

namespace Mews\Pos;

/**
 * Class PosNetCrypt
 * @package Mews\Pos
 */
class PosNetCrypt
{
    /**
     * @var resource
     */
    public $td;

    /**
     * @var int
     */
    public $ks;

    /**
     * @var int
     */
    public $block;

    /**
     * Error message for http connection
     *
     * @access private
     */
    public $error;

    /**
     * PosNetCrypt constructor.
     */
    public function __construct ()
    {
        srand((double) microtime() * 10000000);
        $this->block = @mcrypt_get_block_size(MCRYPT_TripleDES, MCRYPT_MODE_CBC);
        $this->td = @mcrypt_module_open(MCRYPT_TripleDES, '', MCRYPT_MODE_CBC, '');
        $this->ks = @mcrypt_enc_get_key_size($this->td);
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

        // PKCS Padding
        $data = $this->doPadding($data);

        // Initialize
        @mcrypt_generic_init($this->td, $this->detKey($key), $iv);

        // Encrypt Data
        $encrypted_data = @mcrypt_generic($this->td, $data);

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

        if (strlen($data) < 16 + 8) return false;

        // Get IV
        $iv = pack("H*", substr($data, 0, 16));

        // Get Encrypted Data
        $encrypted_data = pack("H*", substr($data, 16, strlen($data)-16-8));

        // Get CRC
        $crc = substr($data, -8);

        // Check CRC
        if (!$this->checkCrc(substr($data, 0, strlen($data)-8), $crc)) {
            $this->error = "CRC is not valid! ($crc)";
            return '';
        }

        // Initialize
        @mcrypt_generic_init($this->td, $this->detKey($key), $iv);

        // Decrypt Data
        $decrypted_data = @mdecrypt_generic($this->td, $encrypted_data);

        // Remove Padded Data
        return $this->removePaddedData($decrypted_data);
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
    public function doPadding($data)
    {
        $len = strlen($data);
        $padding = $this->block - ($len % $this->block);
        $data .= str_repeat(chr($padding), $padding);

        return $data;
    }

    /**
     * @param $data
     * @return bool|string
     */
    public function removePaddedData($data)
    {
        $packing = ord($data { strlen($data) - 1 });

        if ($packing and ($packing < $this->block)) {
            for($P = strlen($data) - 1; $P >= strlen($data) - $packing; $P--) {
                if (ord($data { $P } ) != $packing) {
                    $packing = 0;
                }
            }
        }

        $data = substr($data, 0, strlen($data) - $packing);
        return $data;
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
     *
     */
    public function deInit()
    {
        @mcrypt_generic_deinit($this->td);
        @mcrypt_module_close($this->td);
    }
}
