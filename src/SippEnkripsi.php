<?php

namespace SippEnkripsi;

use InvalidArgumentException;
use phpseclib3\Crypt\Random;
use phpseclib3\Crypt\Rijndael;

/**
 * SippEnkripsi
 *
 * Standalone PHP library for encrypting and decrypting SIPP (Sistem Informasi Penelusuran Perkara)
 * data such as perkara_id, fully backward-compatible with CodeIgniter 2 Encrypt library on PHP 5.
 *
 * Uses phpseclib3 (Rijndael-256 in CBC mode) to replace deprecated/removed PHP mcrypt extension.
 * Compatible with PHP 7.4, 8.0, 8.1, 8.2, 8.3, and newer.
 */
class SippEnkripsi
{
    /**
     * MD5 hash of encryption key
     */
    protected string $encryptionKey = '';

    /**
     * Hash type for cipher noise ('sha1' or 'md5')
     */
    protected string $hashType = 'sha1';

    /**
     * Cipher mode ('cbc' or 'ecb')
     */
    protected string $cipherMode = 'cbc';

    /**
     * Key size in bits (256 bits = 32 bytes)
     */
    protected int $cipherKeySize = 256;

    /**
     * Block size in bits (256 bits = 32 bytes)
     */
    protected int $cipherBlockSize = 256;

    /**
     * Constructor
     *
     * @param string|null $key Optional default encryption key
     */
    public function __construct(?string $key = null)
    {
        if ($key !== null && $key !== '') {
            $this->setKey($key);
        }
    }

    /**
     * Fetch the encryption key as MD5 (32 hex characters / 256-bit representation)
     *
     * @param string|null $key
     * @return string
     * @throws InvalidArgumentException
     */
    public function getKey(?string $key = null): string
    {
        if ($key === null || $key === '') {
            if ($this->encryptionKey !== '') {
                return $this->encryptionKey;
            }

            throw new InvalidArgumentException(
                'Encryption key is not set. Please provide a key via constructor, setKey(), or method parameter.'
            );
        }

        return md5($key);
    }

    /**
     * Alias for getKey() to maintain CodeIgniter compatibility
     *
     * @param string $key
     * @return string
     */
    public function get_key($key = '')
    {
        return $this->getKey($key);
    }

    /**
     * Set the default encryption key
     *
     * @param string $key
     * @return $this
     */
    public function setKey(string $key): self
    {
        $this->encryptionKey = md5($key);
        return $this;
    }

    /**
     * Alias for setKey() to maintain CodeIgniter compatibility
     *
     * @param string $key
     * @return void
     */
    public function set_key($key = '')
    {
        $this->setKey((string) $key);
    }

    /**
     * Encode a string (matches CodeIgniter 2 Encrypt::encode)
     *
     * Encodes the message string using Rijndael-256 with randomized IV and cipher noise,
     * then returns base64 encoded string.
     *
     * @param string|int|float $string String or numeric ID to encode
     * @param string|null $key Optional encryption key
     * @return string Base64-encoded encrypted string
     */
    public function encode($string, ?string $key = null): string
    {
        $key = $this->getKey($key);
        $enc = $this->mcryptEncode((string) $string, $key);

        return base64_encode($enc);
    }

    /**
     * Alias for encode()
     *
     * @param string|int|float $string
     * @param string|null $key
     * @return string
     */
    public function encrypt($string, ?string $key = null): string
    {
        return $this->encode($string, $key);
    }

    /**
     * Decode an encoded string (matches CodeIgniter 2 Encrypt::decode)
     *
     * @param string $string Base64-encoded encrypted string
     * @param string|null $key Optional encryption key
     * @return string|false Decrypted string or false on failure
     */
    public function decode(string $string, ?string $key = null)
    {
        $key = $this->getKey($key);

        if (preg_match('/[^a-zA-Z0-9\/\+=]/', $string)) {
            return false;
        }

        $dec = base64_decode($string, true);
        if ($dec === false) {
            return false;
        }

        return $this->mcryptDecode($dec, $key);
    }

    /**
     * Alias for decode()
     *
     * @param string $string
     * @param string|null $key
     * @return string|false
     */
    public function decrypt(string $string, ?string $key = null)
    {
        return $this->decode($string, $key);
    }

    /**
     * Encode to URL-safe string (replaces +, / with -, _ and removes =)
     * Useful when passing encrypted perkara_id in URLs without corruption
     *
     * @param string|int|float $string
     * @param string|null $key
     * @return string
     */
    public function encodeUrlSafe($string, ?string $key = null): string
    {
        return rtrim(strtr($this->encode($string, $key), '+/', '-_'), '=');
    }

    /**
     * Decode a URL-safe encoded string
     *
     * @param string $string
     * @param string|null $key
     * @return string|false
     */
    public function decodeUrlSafe(string $string, ?string $key = null)
    {
        $string = strtr($string, '-_', '+/');
        $padLength = 4 - (strlen($string) % 4);
        if ($padLength < 4) {
            $string .= str_repeat('=', $padLength);
        }

        return $this->decode($string, $key);
    }

    /**
     * Encode and wrap the result with an extra base64_encode()
     *
     * @param string|int|float $string
     * @param string|null $key
     * @return string
     */
    public function encodeBase64Wrapped($string, ?string $key = null): string
    {
        return base64_encode($this->encode($string, $key));
    }

    /**
     * Alias for encodeBase64Wrapped()
     *
     * @param string|int|float $string
     * @param string|null $key
     * @return string
     */
    public function encodeBase64($string, ?string $key = null): string
    {
        return $this->encodeBase64Wrapped($string, $key);
    }

    /**
     * Decode a string that was wrapped with base64_encode()
     *
     * @param string $string
     * @param string|null $key
     * @return string|false
     */
    public function decodeBase64Wrapped(string $string, ?string $key = null)
    {
        $unwrapped = base64_decode($string, true);
        if ($unwrapped === false) {
            return false;
        }

        return $this->decode($unwrapped, $key);
    }

    /**
     * Alias for decodeBase64Wrapped()
     *
     * @param string $string
     * @param string|null $key
     * @return string|false
     */
    public function decodeBase64(string $string, ?string $key = null)
    {
        return $this->decodeBase64Wrapped($string, $key);
    }

    /**
     * Encrypt using phpseclib Rijndael (compatible with mcrypt MCRYPT_RIJNDAEL_256)
     *
     * @param string $data
     * @param string $key MD5 hash of key
     * @return string
     */
    public function mcryptEncode(string $data, string $key): string
    {
        $cipher = new Rijndael($this->getCipherMode());
        $cipher->setBlockLength($this->cipherBlockSize);
        $cipher->setKeyLength($this->cipherKeySize);
        $cipher->setKey($key);

        // Disable PKCS7 padding to match mcrypt behavior
        $cipher->disablePadding();

        // Null-byte padding to block boundary (matches mcrypt behavior)
        $blockSize = (int) ($this->cipherBlockSize / 8);
        $pad = ($blockSize - (strlen($data) % $blockSize)) % $blockSize;
        if ($pad > 0) {
            $data .= str_repeat("\0", $pad);
        }

        // Generate random IV
        $initSize = (int) ($this->cipherBlockSize / 8);
        $initVect = Random::string($initSize);

        $cipher->setIV($initVect);
        $encrypted = $cipher->encrypt($data);

        return $this->addCipherNoise($initVect . $encrypted, $key);
    }

    /**
     * Alias for mcryptEncode() to maintain CodeIgniter compatibility
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    public function mcrypt_encode($data, $key)
    {
        return $this->mcryptEncode((string) $data, (string) $key);
    }

    /**
     * Decrypt using phpseclib Rijndael (compatible with mcrypt MCRYPT_RIJNDAEL_256)
     *
     * @param string $data Raw binary decrypted from base64
     * @param string $key MD5 hash of key
     * @return string|false
     */
    public function mcryptDecode(string $data, string $key)
    {
        $data = $this->removeCipherNoise($data, $key);
        $initSize = (int) ($this->cipherBlockSize / 8);

        if ($initSize > strlen($data)) {
            return false;
        }

        $initVect = substr($data, 0, $initSize);
        $data = substr($data, $initSize);

        try {
            $cipher = new Rijndael($this->getCipherMode());
            $cipher->setBlockLength($this->cipherBlockSize);
            $cipher->setKeyLength($this->cipherKeySize);
            $cipher->setKey($key);
            $cipher->setIV($initVect);
            $cipher->disablePadding();

            $decrypted = $cipher->decrypt($data);

            // Strip null-byte padding (mcrypt uses zero-padding)
            return rtrim($decrypted, "\0");
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Alias for mcryptDecode() to maintain CodeIgniter compatibility
     *
     * @param string $data
     * @param string $key
     * @return string|false
     */
    public function mcrypt_decode($data, $key)
    {
        return $this->mcryptDecode((string) $data, (string) $key);
    }

    /**
     * Adds permuted noise to the IV + encrypted data to protect
     * against CBC mode manipulation (matches CodeIgniter implementation)
     *
     * @param string $data
     * @param string $key MD5 hash of key
     * @return string
     */
    public function addCipherNoise(string $data, string $key): string
    {
        $keyhash = $this->hash($key);
        $keylen = strlen($keyhash);
        $str = '';

        for ($i = 0, $j = 0, $len = strlen($data); $i < $len; ++$i, ++$j) {
            if ($j >= $keylen) {
                $j = 0;
            }

            $str .= chr((ord($data[$i]) + ord($keyhash[$j])) % 256);
        }

        return $str;
    }

    /**
     * Alias for addCipherNoise()
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function _add_cipher_noise($data, $key)
    {
        return $this->addCipherNoise($data, $key);
    }

    /**
     * Removes permuted noise from the IV + encrypted data
     *
     * @param string $data
     * @param string $key MD5 hash of key
     * @return string
     */
    public function removeCipherNoise(string $data, string $key): string
    {
        $keyhash = $this->hash($key);
        $keylen = strlen($keyhash);
        $str = '';

        for ($i = 0, $j = 0, $len = strlen($data); $i < $len; ++$i, ++$j) {
            if ($j >= $keylen) {
                $j = 0;
            }

            $temp = ord($data[$i]) - ord($keyhash[$j]);
            if ($temp < 0) {
                $temp += 256;
            }

            $str .= chr($temp);
        }

        return $str;
    }

    /**
     * Alias for removeCipherNoise()
     *
     * @param string $data
     * @param string $key
     * @return string
     */
    protected function _remove_cipher_noise($data, $key)
    {
        return $this->removeCipherNoise($data, $key);
    }

    /**
     * Set the Cipher Mode ('cbc' or 'ecb')
     *
     * @param string $mode
     * @return $this
     */
    public function setMode(string $mode): self
    {
        $mode = strtolower($mode);
        if ($mode === 'cbc' || $mode === 'ecb') {
            $this->cipherMode = $mode;
        }

        return $this;
    }

    /**
     * Alias for setMode()
     *
     * @param string|int $mode
     * @return void
     */
    public function set_mode($mode)
    {
        if (defined('MCRYPT_MODE_CBC') && $mode === MCRYPT_MODE_CBC) {
            $mode = 'cbc';
        } elseif (defined('MCRYPT_MODE_ECB') && $mode === MCRYPT_MODE_ECB) {
            $mode = 'ecb';
        }

        $this->setMode((string) $mode);
    }

    /**
     * Get current cipher mode
     *
     * @return string
     */
    public function getCipherMode(): string
    {
        return $this->cipherMode ?: 'cbc';
    }

    /**
     * Alias for getCipherMode()
     *
     * @return string
     */
    protected function _get_mode()
    {
        return $this->getCipherMode();
    }

    /**
     * Set cipher (kept for compatibility)
     *
     * @param mixed $cipher
     * @return $this
     */
    public function setCipher($cipher): self
    {
        return $this;
    }

    /**
     * Alias for setCipher()
     *
     * @param mixed $cipher
     * @return void
     */
    public function set_cipher($cipher)
    {
        $this->setCipher($cipher);
    }

    /**
     * Set hash algorithm for cipher noise ('sha1' or 'md5')
     *
     * @param string $type
     * @return $this
     */
    public function setHash(string $type = 'sha1'): self
    {
        $this->hashType = ($type === 'md5') ? 'md5' : 'sha1';
        return $this;
    }

    /**
     * Alias for setHash()
     *
     * @param string $type
     * @return void
     */
    public function set_hash($type = 'sha1')
    {
        $this->setHash($type);
    }

    /**
     * Hash encode a string
     *
     * @param string $str
     * @return string
     */
    public function hash(string $str): string
    {
        return ($this->hashType === 'sha1') ? $this->sha1($str) : md5($str);
    }

    /**
     * Generate an SHA1 Hash
     *
     * @param string $str
     * @return string
     */
    public function sha1(string $str): string
    {
        return sha1($str);
    }

    /**
     * Encode from legacy CodeIgniter 1.x algorithms
     *
     * @param string $string
     * @param string $legacyMode
     * @param string|null $key
     * @return string|false
     */
    public function encode_from_legacy(string $string, string $legacyMode = 'ecb', ?string $key = null)
    {
        $currentMode = $this->getCipherMode();
        $this->setMode($legacyMode);

        $key = $this->getKey($key);

        if (preg_match('/[^a-zA-Z0-9\/\+=]/', $string)) {
            return false;
        }

        $dec = base64_decode($string, true);
        if ($dec === false) {
            return false;
        }

        $dec = $this->mcryptDecode($dec, $key);
        if ($dec === false) {
            return false;
        }

        $dec = $this->_xor_decode($dec, $key);

        $this->setMode($currentMode);

        return base64_encode($this->mcryptEncode($dec, $key));
    }

    /**
     * XOR Decode (for CI legacy compatibility)
     *
     * @param string $string
     * @param string $key
     * @return string
     */
    public function _xor_decode($string, $key)
    {
        $string = $this->_xor_merge($string, $key);

        $dec = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $dec .= (substr($string, $i++, 1) ^ substr($string, $i, 1));
        }

        return $dec;
    }

    /**
     * XOR key + string Combiner
     *
     * @param string $string
     * @param string $key
     * @return string
     */
    public function _xor_merge($string, $key)
    {
        $hash = $this->hash($key);
        $str = '';
        $hashLen = strlen($hash);
        for ($i = 0; $i < strlen($string); $i++) {
            $str .= substr($string, $i, 1) ^ substr($hash, ($i % $hashLen), 1);
        }

        return $str;
    }
}
