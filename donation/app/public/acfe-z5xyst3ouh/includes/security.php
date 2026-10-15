<?php
/**
 * Module Sécurité & Sessions — Espace Administration ACFE
 * Version durcie : IP fiable, HTTPS canonique, en-têtes stricts, sessions renforcées.
 */

if (!defined('GC_DB_HOST')) {
    $cfg = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
    if (file_exists($cfg)) {
        require_once $cfg;
    }
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (defined('GC_ERROR_LOG_PATH') && GC_ERROR_LOG_PATH !== '') {
    ini_set('error_log', GC_ERROR_LOG_PATH);
}
error_reporting(E_ALL);

function get_client_ip(): string {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trusted = defined('GC_TRUSTED_PROXIES') && is_array(GC_TRUSTED_PROXIES) ? GC_TRUSTED_PROXIES : [];
    if (empty($trusted) || !in_array($remote, $trusted, true)) {
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, $trusted, true)) {
                return $candidate;
            }
        }
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()');
    header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'");
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($is_https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}
send_security_headers();

function enforce_https(): void {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '.local') !== false || strpos($host, '127.0.0.1') !== false || $host === '::1');
    if ($is_local) {
        return;
    }
    $canonical = defined('GC_CANONICAL_HOST') ? GC_CANONICAL_HOST : null;
    if (!$is_https) {
        $target_host = $canonical ?: $host;
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $target_host . $uri, true, 301);
        exit;
    }
    if ($canonical && strcasecmp($host, $canonical) !== 0) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: https://' . $canonical . $uri, true, 301);
        exit;
    }
}
enforce_https();

function enforce_ip_restriction(): void {
    if (!defined('GC_IP_RESTRICTION_ENABLED') || !GC_IP_RESTRICTION_ENABLED) {
        return;
    }
    $client_ip = get_client_ip();
    $allowed = defined('GC_ALLOWED_IPS') && is_array(GC_ALLOWED_IPS) ? GC_ALLOWED_IPS : [];
    if (!in_array($client_ip, $allowed, true)) {
        http_response_code(403);
        die('Accès refusé : votre adresse IP n\'est pas autorisée à accéder à ce portail d\'administration.');
    }
}
enforce_ip_restriction();

function start_secure_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $admin_dir = defined('GC_ADMIN_DIR_NAME') ? GC_ADMIN_DIR_NAME : 'acfe-z5xyst3ouh';
    session_name('ACFE_ADM_SESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    if ($is_https) {
        ini_set('session.cookie_secure', '1');
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/' . $admin_dir,
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    $now = time();
    $lifetime = defined('GC_SESSION_LIFETIME') ? (int)GC_SESSION_LIFETIME : 900;
    if (isset($_SESSION['LAST_ACTIVITY']) && ($now - $_SESSION['LAST_ACTIVITY'] > $lifetime)) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['expired_notice'] = 'Votre session a expiré après 15 minutes d\'inactivité. Veuillez vous reconnecter.';
    }
    $_SESSION['LAST_ACTIVITY'] = $now;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (!isset($_SESSION['USER_AGENT'])) {
        $_SESSION['USER_AGENT'] = $ua;
    } elseif (!hash_equals($_SESSION['USER_AGENT'], $ua)) {
        session_unset();
        session_destroy();
        header('Location: login.php?err=hijack');
        exit;
    }
}
start_secure_admin_session();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function check_admin_auth(): void {
    if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_username'])) {
        header('Location: login.php');
        exit;
    }
}

function require_recent_auth(int $max_age = 300): void {
    check_admin_auth();
    $last = $_SESSION['REAUTH_AT'] ?? 0;
    if ((time() - (int)$last) > $max_age) {
        $_SESSION['reauth_redirect'] = $_SERVER['REQUEST_URI'] ?? 'settings.php';
        header('Location: login.php?reauth=1');
        exit;
    }
}
