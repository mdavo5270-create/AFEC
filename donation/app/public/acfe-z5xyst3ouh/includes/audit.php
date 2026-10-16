<?php
/**
 * Journal d'audit — Espace Administration ACFE
 *
 * Trace qui a exporté ou consulté quoi, quand et depuis quelle adresse IP.
 * Les actions sensibles (export CSV, téléchargement de pièces) échouent si le journal est inaccessible.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/db.php';

function gc_audit_ensure_table(PDO $pdo): void {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS gc_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            admin_username VARCHAR(100) NOT NULL,
            action VARCHAR(60) NOT NULL,
            target VARCHAR(190) NOT NULL DEFAULT '',
            meta TEXT NULL,
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_audit_action (action),
            KEY idx_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $done = true;
}

/**
 * @param array<string,mixed> $meta
 */
function gc_audit(string $action, string $target = '', array $meta = []): bool {
    try {
        $pdo = get_admin_pdo();
        gc_audit_ensure_table($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO gc_audit_log (admin_username, action, target, meta, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            (string)($_SESSION['admin_username'] ?? 'inconnu'),
            $action,
            $target,
            $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            function_exists('get_client_ip') ? get_client_ip() : '',
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('Audit log indisponible : ' . $e->getMessage());
        return false;
    }
}
