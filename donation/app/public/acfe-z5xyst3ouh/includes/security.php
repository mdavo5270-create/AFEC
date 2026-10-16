<?php
/**
 * Module Sécurité & Sessions — Espace Administration ACFE
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

if (!defined('GC_DB_HOST')) {
    require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
}
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/db.php';

ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (defined('GC_ERROR_LOG_PATH') && !empty(GC_ERROR_LOG_PATH)) {
    ini_set('error_log', GC_ERROR_LOG_PATH);
}
error_reporting(E_ALL);

function gc_assert_config(): void {
    $problems = [];
    $must_be_set = ['GC_CSRF_SECRET', 'GC_DB_ADMIN_PASS', 'GC_DB_PUBLIC_PASS'];
    foreach ($must_be_set as $name) {
        $value = defined($name) ? (string)constant($name) : '';
        if ($value === '' || stripos($value, 'CHANGE_ME') === 0) {
            $problems[] = $name;
        }
    }
    if (defined('GC_CSRF_SECRET') && strlen((string)GC_CSRF_SECRET) < 32) {
        $problems[] = 'GC_CSRF_SECRET (32 caractères minimum)';
    }
    if ($problems) {
        error_log('Configuration ACFE invalide : ' . implode(', ', $problems));
        http_response_code(500);
        die('Configuration de sécurité incomplète. Contactez l\'administrateur système.');
    }
}
gc_assert_config();

function gc_trusted_proxies(): array {
    return defined('GC_TRUSTED_PROXIES') ? (array)GC_TRUSTED_PROXIES : [];
}

function gc_remote_addr(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function gc_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    return !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
        && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https'
        && in_array(gc_remote_addr(), gc_trusted_proxies(), true);
}

function get_client_ip(): string {
    $ip      = gc_remote_addr();
    $trusted = gc_trusted_proxies();
    if ($trusted && in_array($ip, $trusted, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_map('trim', explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']));
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = $chain[$i];
            if (!filter_var($candidate, FILTER_VALIDATE_IP) || in_array($candidate, $trusted, true)) {
                continue;
            }
            return $candidate;
        }
    }
    return $ip;
}

function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 0");
    header("Referrer-Policy: no-referrer");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=(), usb=()");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("Cross-Origin-Resource-Policy: same-origin");
    header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet");
    header("Cache-Control: no-store, no-cache, must-revalidate, private");
    header("Pragma: no-cache");
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none';");
    if (gc_is_https()) {
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
    }
}
send_security_headers();

function gc_is_local_request(): bool {
    $host = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $host_is_local = ($host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]' || substr($host, -6) === '.local');
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $remote_is_valid   = filter_var($remote, FILTER_VALIDATE_IP) !== false;
    $remote_is_private = $remote_is_valid
        && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    return $host_is_local && $remote_is_private;
}

function enforce_https(): void {
    if (gc_is_https() || gc_is_local_request()) {
        return;
    }
    $host = (defined('GC_CANONICAL_HOST') && GC_CANONICAL_HOST !== '') ? (string)GC_CANONICAL_HOST : (string)($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d{1,5})?$/', $host)) {
        http_response_code(400);
        exit;
    }
    $uri = preg_replace('/[\r\n]+/', '', (string)($_SERVER['REQUEST_URI'] ?? '/'));
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}
enforce_https();

function enforce_ip_restriction(): void {
    if (!defined('GC_IP_RESTRICTION_ENABLED') || !GC_IP_RESTRICTION_ENABLED) {
        return;
    }
    $client_ip = get_client_ip();
    $allowed   = defined('GC_ALLOWED_IPS') ? GC_ALLOWED_IPS : [];
    if (!in_array($client_ip, $allowed, true)) {
        http_response_code(403);
        die("Accès interdit : cette adresse IP n'est pas autorisée à accéder à ce portail d'administration.");
    }
}
enforce_ip_restriction();

function start_secure_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $script_dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $cookie_path = $script_dir !== '' ? $script_dir : ('/' . (defined('GC_ADMIN_DIR_NAME') ? GC_ADMIN_DIR_NAME : ''));
    session_name('ACFE_ADM_SESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $cookie_path,
        'domain'   => '',
        'secure'   => gc_is_https() || !gc_is_local_request(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    $now      = time();
    $lifetime = defined('GC_SESSION_LIFETIME') ? (int)GC_SESSION_LIFETIME : 900;
    $absolute = defined('GC_SESSION_ABSOLUTE') ? (int)GC_SESSION_ABSOLUTE : 28800;
    $expired_idle     = isset($_SESSION['LAST_ACTIVITY']) && ($now - $_SESSION['LAST_ACTIVITY'] > $lifetime);
    $expired_absolute = isset($_SESSION['LOGIN_TIME']) && ($now - $_SESSION['LOGIN_TIME'] > $absolute);
    if ($expired_idle || $expired_absolute) {
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['expired_notice'] = $expired_absolute
            ? "Votre session a atteint sa durée maximale. Veuillez vous reconnecter."
            : "Votre session a expiré après " . (int)round($lifetime / 60) . " minutes d'inactivité. Veuillez vous reconnecter.";
    }
    $_SESSION['LAST_ACTIVITY'] = $now;
}
start_secure_admin_session();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function gc_session_epoch_from_db(PDO $pdo, int $user_id): ?int {
    try {
        $stmt = $pdo->prepare("SELECT session_epoch FROM gc_admin_users WHERE id = ? LIMIT 1");
        $stmt->execute([$user_id]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    } catch (PDOException $e) {
        return null;
    }
}

function gc_invalidate_other_sessions(PDO $pdo, int $user_id): void {
    try {
        $pdo->prepare("UPDATE gc_admin_users SET session_epoch = session_epoch + 1 WHERE id = ?")->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("session_epoch indisponible (migration à appliquer) : " . $e->getMessage());
        return;
    }
    $epoch = gc_session_epoch_from_db($pdo, $user_id);
    if ($epoch !== null) {
        $_SESSION['session_epoch'] = $epoch;
    }
}

function check_admin_auth(): void {
    if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_username'])) {
        header("Location: login.php");
        exit;
    }
    $db_epoch = gc_session_epoch_from_db(get_admin_pdo(), (int)$_SESSION['admin_user_id']);
    if ($db_epoch !== null && (int)($_SESSION['session_epoch'] ?? -1) !== $db_epoch) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
    if (!empty($_SESSION['must_setup_2fa'])) {
        $script = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!in_array($script, ['settings.php', 'logout.php'], true)) {
            header("Location: settings.php");
            exit;
        }
    }
}

function gc_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        die('Méthode non autorisée.');
    }
}
