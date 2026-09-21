<?php

/**
 * A simple class that relies on OpenSSL to encrypt/decrypt data.
 */
class Crypto {

    private $cipher = "AES-256-CBC";
    private $blobKey = null;
    private $hmacKey = null;

    /**
     * @param string $blobKey Encryption key (base64-encoded, 32 bytes)
     * @param string $hmacKey Checksum key (base64-encoded, 32 bytes)
     */
    private function __construct($blobKey, $hmacKey)
    {
        $this->blobKey = $blobKey;
        $this->hmacKey = $hmacKey;
    }

    /**
     * Gets an instance of the Crypto class.
     * Keys are auto-gernerated based on context.
     * 
     * @param string $blobKey Encryption key (optional)
     * @param string $hmacKey Checksum key (optional)
     */
    public static function init($blobKey = null, $hmacKey = null) {
        if (!function_exists("openssl_encrypt")) {
            throw new Exception("OpenSSL functions are not available!");
        }
        if ((empty($blobKey) && !empty($hmacKey)) || (!empty($blobKey) && empty($hmacKey))) {
            throw new Exception("Either both, encryption and checksumc keys must be supplied, or neither.");
        }
        if (empty($blobKey)) {
            // Auto-generate
            $rc_salt = $GLOBALS["salt"];
            // For user, use USERID instead of $GLOBALS["userid"] as their value may be different in Shibboleth environments
            $userid = defined("USERID") ? USERID : "[Unknown User]";
            $proj_salt = isset($GLOBALS["Proj"]) ? $GLOBALS["Proj"]->project["__SALT__"] : hash("sha256", "SystemSalt".APP_PATH_DOCROOT);
            $proj_id = isset($GLOBALS["Proj"]) ? $GLOBALS["Proj"]->project_id : hash("sha256", "SystemContext");
            $blobKey = hash("sha256", "BlobKey-$rc_salt-$userid-$proj_salt-$proj_id");
            $hmacKey = hash("sha256", "HmacKey-$blobKey");
        }
        // Ensure minimal length of keys
        while (strlen($blobKey) < 32) {
            $blobKey = $blobKey.$blobKey;
        }
        while (strlen($hmacKey) < 32) {
            $hmacKey = $hmacKey.$hmacKey;
        }
        $blobKey = base64_encode(substr($blobKey, 0, 32));
        $hmacKey = base64_encode(substr($hmacKey, 0, 32));
        if ($blobKey == $hmacKey) {
            throw new Exception("The first 32 bytes of the encryption and checksum keys must not be identical!");
        }
        return new self($blobKey, $hmacKey);
    }


    /**
     * Encrytps data using AES-256-CBC.
     * @param mixed $data The data to be encrypted. It must be JSON-encodable.
     * @return string Base64-encoded encrypted blob.
     */
    public function encrypt($data)
    {
        $this->checkKeys();
        $payload = array (
            "data" => $data,
            // Add some random data to ensure same content will always give a different result
            "random" => base64_encode(openssl_random_pseudo_bytes(20))
        );
        $jsonData = json_encode($payload);
        $key = base64_decode($this->blobKey);
        $ivLen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLen);
        $aesData = openssl_encrypt($jsonData, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $aesData, $this->hmacKey, true);
        $blob = base64_encode($iv.$hmac.$aesData);
        return $blob;
    }

    /**
     * Decrypts a base64-encoded blob.
     * @param string $blob The encrypted blob (base64-encoded).
     * @return mixed the original data.
     */
    public function decrypt($blob) 
    {
        $this->checkKeys();
        $raw = base64_decode($blob);
        $key = base64_decode($this->blobKey);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($raw, 0, $ivlen);
        $blobHmac = substr($raw, $ivlen, 32);
        $aesData = substr($raw, $ivlen + 32);
        $jsonData = openssl_decrypt($aesData, $this->cipher, $key, OPENSSL_RAW_DATA, $iv);
        $calcHmac = hash_hmac('sha256', $aesData, $this->hmacKey, true);
        // Only return data if the hashes match.
        if (is_string($blobHmac) && is_string($calcHmac) && hash_equals($blobHmac, $calcHmac)) {
            $payload = json_decode($jsonData, true);
            $data = $payload["data"];
            return $data;
        }
        return null;
    }

    private function checkKeys() 
    {
        if (!strlen($this->blobKey) || !strlen($this->hmacKey)) {
            throw new Exception("Must set keys first!");
        }
        if (strlen(base64_decode($this->blobKey)) != 32) {
            throw new Exception("Encryption key is not of the correct size");
        }
        if (strlen(base64_decode($this->hmacKey)) != 32) {
            throw new Exception("Checksum key is not of the correct size");
        }
        if ($this->blobKey == $this->hmacKey) {
            throw new Exception("Encryption and checksum keys must not be identical");
        }
    }

    /**
     * Generates a key (32 bytes) that can be used for encryption.
     */
    public static function genKey()
    {
        $key = openssl_random_pseudo_bytes(32);
        return base64_encode($key);
    }

    /**
     * Generates a Guid in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     * @return string A Guid in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     */
    public static function getGuid() 
    {
        if (function_exists('com_create_guid') === true) {
            return strtolower(trim(com_create_guid(), '{}'));
        }
        return strtolower(sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535)));
    }
}