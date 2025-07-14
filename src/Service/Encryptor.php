<?php
declare(strict_types=1);

namespace Implementa2\A3Integra\Service;

class Encryptor
{
    private const METHOD = 'AES-256-CBC';
    /** @var string */
    private $key;

    public function __construct()
    {
        // La clave maestra de la tienda
        if (!defined('_COOKIE_KEY_') || empty(_COOKIE_KEY_)) {
            throw new \RuntimeException('_COOKIE_KEY_ no está definida');
        }
        // OpenSSL AES‑256 necesita 32 bytes de key
        $this->key = substr(hash('sha256', _COOKIE_KEY_), 0, 32);
    }

    /**
     * Cifra $plaintext y devuelve un string seguro (base64(iv+data))
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(openssl_cipher_iv_length(self::METHOD));
        $cipher = openssl_encrypt($plaintext, self::METHOD, $this->key, OPENSSL_RAW_DATA, $iv);
        // concatenamos IV + data y lo codificamos
        return base64_encode($iv . $cipher);
    }

    /**
     * Descifra un string previamente cifrado con encrypt()
     */
    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode($ciphertext, true);
        if ($raw === false) {
            throw new \RuntimeException('Formato de ciphertext inv&aacute;lido');
        }
        $ivLen = openssl_cipher_iv_length(self::METHOD);
        $iv = substr($raw, 0, $ivLen);
        $data = substr($raw, $ivLen);
        $plain = openssl_decrypt($data, self::METHOD, $this->key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false) {
            throw new \RuntimeException('Error al descifrar datos');
        }
        return $plain;
    }
}
