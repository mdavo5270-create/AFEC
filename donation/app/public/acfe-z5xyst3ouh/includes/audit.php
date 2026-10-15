<?php
/**
 * Journal d'audit — actions sensibles de l'administration.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

function gc_audit(string $action, array $details = []): void {
    try {
        $pdo = get_admin_pdo();
        $stmt = $pdo->prepare("
            INSERT INTO gc_audit_log (user_id, username, action, details, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $user_id  = $_SESSION['admin_user_id'] ?? null;
        $username = $_SESSION['admin_username'] ?? null;
        $ip       = get_client_ip();
        $ua       = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        $json     = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([$user_id, $username, $action, $json, $ip, $ua]);
    } catch (Throwable $e) {
        error_log('gc_audit failed: ' . $e->getMessage());
    }
}
