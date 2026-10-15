<?php
/**
 * Chiffrement des secrets sensibles (2FA, etc.)
 * Utilise sodium (libsodium) si disponible, sinon OpenSSL AES-256-GCM.
 */

function gc_crypto_key(): string {
    $secret = defined('GC_CSRF_SECRET') ? GC_CSRF_SECRET : '';
    if (strlen($secret) < 32) {
        $secret = hash('sha256', __DIR__ . php_uname(), true);
    }
    return hash('sha256', $secret . '|acfe-crypto-v1', true);
}

function gc_encrypt(string $plaintext): string {
    $key = gc_crypto_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return base64_encode($nonce . $cipher);
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Échec du chiffrement');
    }
    return base64_encode($iv . $tag . $cipher);
}

function gc_decrypt(string $encoded): string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        throw new RuntimeException('Données chiffrées invalides');
    }
    $key = gc_crypto_key();
    if (function_exists('sodium_crypto_secretbox_open')) {
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            throw new RuntimeException('Échec du déchiffrement');
        }
        return $plain;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Échec du déchiffrement');
    }
    return $plain;
}
