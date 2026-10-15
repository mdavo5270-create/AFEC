<?php
/**
 * Module d'Authentification & Brute-Force Protection — ACFE Admin
 * Durci : rate-limit par IP + username, 2FA chiffrée, ré-auth.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/audit.php';

function is_ip_locked(string $ip, ?string $username = null): array {
    $pdo = get_admin_pdo();
    $lockout_seconds = defined('GC_LOCKOUT_TIME_SECONDS') ? (int)GC_LOCKOUT_TIME_SECONDS : 900;
    $max_attempts    = defined('GC_MAX_LOGIN_ATTEMPTS') ? (int)GC_MAX_LOGIN_ATTEMPTS : 5;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
        FROM gc_login_attempts
        WHERE ip_address = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)
    ");
    $stmt->execute([$ip, $lockout_seconds]);
    $row = $stmt->fetch();
    $failed_count = (int)($row['failed_count'] ?? 0);

    if ($failed_count >= $max_attempts) {
        $last = strtotime($row['last_attempt'] ?? 'now');
        $remaining = $lockout_seconds - (time() - $last);
        if ($remaining > 0) {
            return ['locked' => true, 'remaining' => (int)ceil($remaining / 60)];
        }
    }

    if ($username !== null && $username !== '') {
        $stmt2 = $pdo->prepare("
            SELECT COUNT(*) as failed_count, MAX(attempted_at) as last_attempt
            FROM gc_login_attempts
            WHERE username = ? AND success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)
        ");
        $stmt2->execute([$username, $lockout_seconds]);
        $row2 = $stmt2->fetch();
        $failed_u = (int)($row2['failed_count'] ?? 0);
        if ($failed_u >= $max_attempts) {
            $last = strtotime($row2['last_attempt'] ?? 'now');
            $remaining = $lockout_seconds - (time() - $last);
            if ($remaining > 0) {
                return ['locked' => true, 'remaining' => (int)ceil($remaining / 60)];
            }
        }
    }

    return ['locked' => false, 'remaining' => 0];
}

function record_login_attempt(string $ip, string $username, bool $success): void {
    $pdo = get_admin_pdo();
    $stmt = $pdo->prepare("INSERT INTO gc_login_attempts (ip_address, username, attempted_at, success) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$ip, $username, $success ? 1 : 0]);
}

function attempt_login(string $username, string $password, ?string $code_2fa = null): array {
    $ip = get_client_ip();
    $username = trim($username);

    $lock_status = is_ip_locked($ip, $username);
    if ($lock_status['locked']) {
        gc_audit('login_locked', ['username' => $username, 'ip' => $ip]);
        return [
            'success' => false,
            'error'   => 'Trop de tentatives infructueuses. Accès temporairement bloqué pendant encore ' . $lock_status['remaining'] . ' minute(s).'
        ];
    }

    $pdo = get_admin_pdo();
    $stmt = $pdo->prepare("SELECT * FROM gc_admin_users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($ip, $username, false);
        gc_audit('login_failed', ['username' => $username]);
        return [
            'success' => false,
            'error'   => 'Identifiants invalides. Veuillez vérifier votre nom d\'utilisateur et votre mot de passe.'
        ];
    }

    $has_2fa = !empty($user['totp_secret_enc']) || !empty($user['totp_secret']);
    if ($has_2fa) {
        if ($code_2fa === null || $code_2fa === '') {
            return [
                'success' => false,
                'need_2fa' => true,
                'error'   => 'Veuillez saisir le code d\'authentification à deux facteurs.'
            ];
        }
        $secret = '';
        try {
            if (!empty($user['totp_secret_enc'])) {
                $secret = gc_decrypt($user['totp_secret_enc']);
            } else {
                $secret = (string)$user['totp_secret'];
            }
        } catch (Throwable $e) {
            error_log('2FA decrypt failed for user ' . $user['id']);
            return ['success' => false, 'error' => 'Erreur de vérification 2FA. Contactez l\'administrateur.'];
        }
        if (!verify_totp_code($secret, $code_2fa)) {
            record_login_attempt($ip, $username, false);
            gc_audit('login_2fa_failed', ['username' => $username]);
            return [
                'success' => false,
                'need_2fa' => true,
                'error'   => 'Code d\'authentification à deux facteurs incorrect.'
            ];
        }
    }

    session_regenerate_id(true);

    $_SESSION['admin_user_id']  = (int)$user['id'];
    $_SESSION['admin_username'] = (string)$user['username'];
    $_SESSION['admin_email']    = (string)$user['email'];
    $_SESSION['admin_role']     = (string)$user['role'];
    $_SESSION['LAST_ACTIVITY']  = time();
    $_SESSION['REAUTH_AT']      = time();

    $upd = $pdo->prepare("UPDATE gc_admin_users SET last_login = NOW() WHERE id = ?");
    $upd->execute([$user['id']]);

    record_login_attempt($ip, $username, true);
    gc_audit('login_success', ['username' => $username]);

    return ['success' => true];
}

function verify_totp_code(string $secret, string $code): bool {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    $time_slice = (int)floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(get_totp_code($secret, $time_slice + $i), $code)) {
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

function generate_totp_secret(int $length = 16): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}
