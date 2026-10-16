<?php
/**
 * Module d'Authentification & Brute-Force Protection — ACFE Admin
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/** Hash bcrypt factice : permet de dépenser le même temps de calcul quand l'utilisateur n'existe pas. */
const GC_DUMMY_PASSWORD_HASH = '$2y$12$vzu.4Jjj3bCjIKo7uBi6I..GPrMghBP2tQPdq9l3fKYQ6AxmTWI.i';

function is_login_locked(string $ip, string $username = ''): array {
    $pdo = get_admin_pdo();
    $lockout_seconds = defined('GC_LOCKOUT_TIME_SECONDS') ? (int)GC_LOCKOUT_TIME_SECONDS : 900;
    $max_attempts    = defined('GC_MAX_LOGIN_ATTEMPTS') ? (int)GC_MAX_LOGIN_ATTEMPTS : 5;

    $checks = [
        ['ip_address = ?', $ip, $max_attempts],
    ];
    if ($username !== '') {
        $checks[] = ['username = ?', $username, $max_attempts * 2];
    }

    $remaining_max = 0;
    foreach ($checks as [$where, $value, $threshold]) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS failed_count, MAX(attempted_at) AS last_attempt
            FROM gc_login_attempts
            WHERE $where AND success = 0 AND attempted_at > (NOW() - INTERVAL ? SECOND)
        ");
        $stmt->execute([$value, $lockout_seconds]);
        $row = $stmt->fetch();

        if ((int)($row['failed_count'] ?? 0) >= $threshold && !empty($row['last_attempt'])) {
            $remaining = $lockout_seconds - (time() - strtotime($row['last_attempt']));
            if ($remaining > $remaining_max) {
                $remaining_max = $remaining;
            }
        }
    }

    if ($remaining_max > 0) {
        return ['locked' => true, 'remaining' => (int)ceil($remaining_max / 60)];
    }
    return ['locked' => false, 'remaining' => 0];
}

function is_ip_locked(string $ip): array {
    return is_login_locked($ip);
}

function record_login_attempt(string $ip, string $username, bool $success): void {
    $pdo = get_admin_pdo();
    $stmt = $pdo->prepare("INSERT INTO gc_login_attempts (ip_address, username, attempted_at, success) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$ip, mb_substr($username, 0, 100), $success ? 1 : 0]);
    if (random_int(1, 50) === 1) {
        $pdo->exec("DELETE FROM gc_login_attempts WHERE attempted_at < (NOW() - INTERVAL 30 DAY)");
    }
}

function attempt_login(string $username, string $password, ?string $code_2fa = null): array {
    $ip = get_client_ip();
    $lock_status = is_login_locked($ip, $username);
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

    $hash_to_check = $user ? (string)$user['password_hash'] : GC_DUMMY_PASSWORD_HASH;
    $password_ok   = password_verify($password, $hash_to_check);

    if (!$user || !$password_ok) {
        record_login_attempt($ip, $username, false);
        return [
            'success' => false,
            'error'   => "Identifiants invalides. Veuillez vérifier votre nom d'utilisateur et votre mot de passe."
        ];
    }

    $has_2fa = !empty($user['two_factor_enabled']) && !empty($user['two_factor_secret']);
    if ($has_2fa) {
        if (empty($code_2fa)) {
            return [
                'success' => false,
                'require_2fa' => true,
                'error' => "Veuillez saisir votre code d'authentification à deux facteurs (2FA)."
            ];
        }

        $secret = gc_decrypt_secret((string)$user['two_factor_secret']);
        if ($secret === null) {
            error_log("2FA illisible pour le compte #" . (int)$user['id'] . " : réactivation nécessaire par un administrateur système.");
            return [
                'success' => false,
                'error'   => "Le 2FA de ce compte est indisponible. Contactez l'administrateur système."
            ];
        }

        if (!verify_totp_code($secret, $code_2fa)) {
            record_login_attempt($ip, $username, false);
            return [
                'success' => false,
                'require_2fa' => true,
                'error' => "Code 2FA invalide ou expiré."
            ];
        }

        if (!gc_is_encrypted_secret((string)$user['two_factor_secret'])) {
            $mig = $pdo->prepare("UPDATE gc_admin_users SET two_factor_secret = ? WHERE id = ?");
            $mig->execute([gc_encrypt_secret($secret), $user['id']]);
        }
    }

    session_regenerate_id(true);
    unset($_SESSION['csrf_token'], $_SESSION['pending_2fa_secret'], $_SESSION['expired_notice']);

    $_SESSION['admin_user_id']  = (int)$user['id'];
    $_SESSION['admin_username'] = (string)$user['username'];
    $_SESSION['admin_email']    = (string)$user['email'];
    $_SESSION['admin_role']     = (string)$user['role'];
    $_SESSION['LAST_ACTIVITY']  = time();
    $_SESSION['LOGIN_TIME']     = time();
    $_SESSION['session_epoch']  = (int)($user['session_epoch'] ?? 0);

    if ($has_2fa) {
        unset($_SESSION['must_setup_2fa']);
    } else {
        $_SESSION['must_setup_2fa'] = true;
    }

    if (password_needs_rehash((string)$user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $rehash = $pdo->prepare("UPDATE gc_admin_users SET password_hash = ? WHERE id = ?");
        $rehash->execute([password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]), $user['id']]);
    }
    $upd = $pdo->prepare("UPDATE gc_admin_users SET last_login = NOW() WHERE id = ?");
    $upd->execute([$user['id']]);

    record_login_attempt($ip, $username, true);
    return ['success' => true];
}

function gc_reauthenticate_current_admin(string $password): array {
    $ip       = get_client_ip();
    $username = (string)($_SESSION['admin_username'] ?? '');

    $lock = is_login_locked($ip, $username);
    if ($lock['locked']) {
        return ['ok' => false, 'error' => "Trop de tentatives. Réessayez dans " . $lock['remaining'] . " minute(s)."];
    }

    $pdo  = get_admin_pdo();
    $stmt = $pdo->prepare("SELECT password_hash FROM gc_admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)($_SESSION['admin_user_id'] ?? 0)]);
    $hash = $stmt->fetchColumn();

    if (!is_string($hash) || !password_verify($password, $hash)) {
        record_login_attempt($ip, $username, false);
        return ['ok' => false, 'error' => "Mot de passe incorrect."];
    }
    return ['ok' => true, 'error' => ''];
}

function verify_totp_code(string $secret, string $code): bool {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code) || strlen($secret) < 16) {
        return false;
    }
    $time_slice = (int)floor(time() / 30);
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
