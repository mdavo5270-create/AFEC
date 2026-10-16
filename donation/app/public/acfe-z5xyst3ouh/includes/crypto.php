<?php
/**
 * Utilitaires cryptographiques — Espace Administration ACFE
 *
 * - Dérivation de clés par usage (aucun secret brut réutilisé entre fonctions).
 * - Chiffrement des secrets 2FA au repos (libsodium, inclus dans PHP >= 7.2).
 * - Jetons de téléchargement signés, liés à l'administrateur et à durée limitée.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

function gc_master_secret(): string {
    if (defined('GC_DATA_KEY') && is_string(GC_DATA_KEY) && GC_DATA_KEY !== '') {
        return GC_DATA_KEY;
    }
    return (string)GC_CSRF_SECRET;
}

function gc_derive_key(string $purpose): string {
    return hash_hmac('sha256', 'acfe|' . $purpose, gc_master_secret(), true);
}

function gc_is_encrypted_secret(string $stored): bool {
    return strncmp($stored, 'enc1:', 5) === 0;
}

function gc_encrypt_secret(string $plain): string {
    if (!function_exists('sodium_crypto_secretbox')) {
        throw new RuntimeException('Extension sodium indisponible : chiffrement impossible.');
    }
    $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($plain, $nonce, gc_derive_key('2fa-secret-v1'));
    return 'enc1:' . base64_encode($nonce . $cipher);
}

function gc_decrypt_secret(string $stored): ?string {
    if (!gc_is_encrypted_secret($stored)) {
        return $stored;
    }
    if (!function_exists('sodium_crypto_secretbox_open')) {
        return null;
    }
    $raw = base64_decode(substr($stored, 5), true);
    $min = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;
    if ($raw === false || strlen($raw) < $min) {
        return null;
    }
    $nonce  = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $plain  = sodium_crypto_secretbox_open($cipher, $nonce, gc_derive_key('2fa-secret-v1'));
    return $plain === false ? null : $plain;
}

function gc_download_token(int $cand_id, string $type, int $expires, int $admin_id): string {
    return hash_hmac('sha256', "dl|{$cand_id}|{$type}|{$expires}|{$admin_id}", gc_derive_key('download-v1'));
}

function gc_download_url(int $cand_id, string $type): string {
    $admin_id = (int)($_SESSION['admin_user_id'] ?? 0);
    $expires  = time() + 600;
    $token    = gc_download_token($cand_id, $type, $expires, $admin_id);
    return 'download.php?' . http_build_query([
        'id'    => $cand_id,
        'type'  => $type,
        'exp'   => $expires,
        'token' => $token,
    ]);
}

function gc_verify_download_token(int $cand_id, string $type, int $expires, string $token): bool {
    if ($expires < time() || $expires > time() + 3600) {
        return false;
    }
    $admin_id = (int)($_SESSION['admin_user_id'] ?? 0);
    return hash_equals(gc_download_token($cand_id, $type, $expires, $admin_id), $token);
}
