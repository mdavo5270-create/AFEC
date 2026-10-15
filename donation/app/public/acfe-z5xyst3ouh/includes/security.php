<?php
/**
 * Module Sécurité & Sessions — Espace Administration ACFE
 */

if (!defined('GC_DB_HOST')) {
    require_once dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
}

// 1. Désactivation stricte de l'affichage des erreurs en production & journalisation sécurisée
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
if (defined('GC_ERROR_LOG_PATH') && !empty(GC_ERROR_LOG_PATH)) {
    ini_set('error_log', GC_ERROR_LOG_PATH);
}
error_reporting(E_ALL);

// 2. En-têtes HTTP de sécurité
function send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
    header("X-Robots-Tag: noindex, nofollow, noarchive, nosnippet");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; frame-ancestors 'none'; form-action 'self';");
}
send_security_headers();

// 3. Forcer HTTPS en production
function enforce_https(): void {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    // On n'impose pas le forçage HTTPS sur localhost ou .local si SSL local n'est pas activé
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '.local') !== false || strpos($host, '127.0.0.1') !== false);

    if (!$is_https && !$is_local) {
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: " . $redirect_url, true, 301);
        exit;
    }
}
enforce_https();

// 4. Filtrage & Restriction par Adresse IP
function enforce_ip_restriction(): void {
    if (!defined('GC_IP_RESTRICTION_ENABLED') || !GC_IP_RESTRICTION_ENABLED) {
        return;
    }
    $client_ip = get_client_ip();
    $allowed = defined('GC_ALLOWED_IPS') ? GC_ALLOWED_IPS : [];
    if (!in_array($client_ip, $allowed, true)) {
        http_response_code(403);
        die("Accès interdit : Votre adresse IP ($client_ip) n'est pas autorisée à accéder à ce portail d'administration.");
    }
}

function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
enforce_ip_restriction();

// 5. Configuration de session ultra-sécurisée
function start_secure_admin_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_name('ACFE_ADM_SESS');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0, // Expire à la fermeture du navigateur
        'path'     => '/' . GC_ADMIN_DIR_NAME,
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_start();

    // Vérification de l'expiration après inactivité
    $now = time();
    $lifetime = defined('GC_SESSION_LIFETIME') ? GC_SESSION_LIFETIME : 900;

    if (isset($_SESSION['LAST_ACTIVITY']) && ($now - $_SESSION['LAST_ACTIVITY'] > $lifetime)) {
        // Session expirée
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['expired_notice'] = "Votre session a expiré après 15 minutes d'inactivité. Veuillez vous reconnecter.";
    }
    $_SESSION['LAST_ACTIVITY'] = $now;

    // Protection contre le vol de session (User-Agent fingerprint)
    if (!isset($_SESSION['USER_AGENT'])) {
        $_SESSION['USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['USER_AGENT'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_unset();
        session_destroy();
        header("Location: login.php?err=hijack");
        exit;
    }
}
start_secure_admin_session();

// 6. Échappement XSS universel
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// 7. Vérification de l'authentification admin
function check_admin_auth(): void {
    if (empty($_SESSION['admin_user_id']) || empty($_SESSION['admin_username'])) {
        header("Location: login.php");
        exit;
    }
}
