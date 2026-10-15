<?php
/**
 * Module d'Authentification & Brute-Force Protection — ACFE Admin
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// 1. Vérification du verrouillage temporaire anti brute-force
function is_ip_locked(string $ip): array {
    $pdo = get_admin_pdo();
    $lockout_seconds = defined('GC_LOCKOUT_TIME_SECONDS') ? GC_LOCKOUT_TIME_SECONDS : 900;
    $max_attempts    = defined('GC_MAX_LOGIN_ATTEMPTS') ? GC_MAX_LOGIN_ATTEMPTS : 5;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
        FROM gc_login_attempts
        WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip, $lockout_seconds]);
    $row = $stmt->fetch();

    $failed_count = (int)($row['failed_count'] ?? 0);
    if ($failed_count >= $max_attempts) {
        $last_attempt_time = strtotime($row['last_attempt']);
        $remaining = $lockout_seconds - (time() - $last_attempt_time);
        if ($remaining > 0) {
            return ['locked' => true, 'remaining' => ceil($remaining / 60)];
        }
    }
    return ['locked' => false, 'remaining' => 0];
}

// 2. Enregistrement d'une tentative de connexion
function record_login_attempt(string $ip, string $username, bool $success): void {
    $pdo = get_admin_pdo();
    $stmt = $pdo->prepare("INSERT INTO gc_login_attempts (ip_address, username, attempted_at, success) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$ip, $username, $success ? 1 : 0]);
}

// 3. Authentification sécurisée de l'administrateur
function attempt_login(string $username, string $password, ?string $code_2fa = null): array {
    $ip = get_client_ip();
    $lock_status = is_ip_locked($ip);
    if ($lock_status['locked']) {
        return [
            'success' => false,
            'error'   => "Trop de tentatives infructueuses. Accès temporairement bloqué pendant encore " . $lock_status['remaining'] . " minute(s)."
        ];
    }

    $pdo = get_admin_pdo();
    $stmt = $pdo->prepare("SELECT * FROM gc_admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($ip, $username, false);
        return [
            'success' => false,
            'error'   => "Identifiants invalides. Veuillez vérifier votre nom d'utilisateur et votre mot de passe."
        ];
    }

    // Vérification du 2FA si activé sur le compte
    if (!empty($user['two_factor_enabled']) && !empty($user['two_factor_secret'])) {
        if (empty($code_2fa)) {
            return [
                'success' => false,
                'require_2fa' => true,
                'error' => "Veuillez saisir votre code d'authentification à deux facteurs (2FA)."
            ];
        }
        if (!verify_totp_code($user['two_factor_secret'], $code_2fa)) {
            record_login_attempt($ip, $username, false);
            return [
                'success' => false,
                'require_2fa' => true,
                'error' => "Code 2FA invalide ou expiré."
            ];
        }
    }

    // Réussite de la connexion : réinitialiser l'ID de session (anti-session fixation)
    session_regenerate_id(true);

    $_SESSION['admin_user_id']  = (int)$user['id'];
    $_SESSION['admin_username'] = (string)$user['username'];
    $_SESSION['admin_email']    = (string)$user['email'];
    $_SESSION['admin_role']     = (string)$user['role'];
    $_SESSION['LAST_ACTIVITY']  = time();

    // Mettre à jour last_login
    $upd = $pdo->prepare("UPDATE gc_admin_users SET last_login = NOW() WHERE id = ?");
    $upd->execute([$user['id']]);

    record_login_attempt($ip, $username, true);

    return ['success' => true];
}

// 4. Implémentation TOTP standard RFC 6238 pour 2FA
function verify_totp_code(string $secret, string $code): bool {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    $time_slice = floor(time() / 30);
    // Vérifier la fenêtre actuelle, -1 et +1 pour tolérer un léger décalage d'horloge
    for ($i = -1; $i <= 1; $i++) {
        $calculated = get_totp_code($secret, $time_slice + $i);
        if (hash_equals($calculated, $code)) {
            return true;
        }
    }
    return false;
}

function get_totp_code(string $secret, int $time_slice): string {
    $secret_key = base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $time_slice);
    $hash = hash_hmac('sha1', $time, $secret_key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncated = substr($hash, $offset, 4);
    $value = unpack('N', $truncated)[1] & 0x7FFFFFFF;
    return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
}

function base32_decode(string $b32): string {
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $binary .= chr(bindec(substr($bits, $i, 8)));
    }
    return $binary;
}
