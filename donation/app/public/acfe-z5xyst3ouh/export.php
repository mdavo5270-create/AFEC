<?php
/**
 * Export CSV Sécurisé — ACFE Admin
 *
 * - Requête POST uniquement, avec jeton CSRF.
 * - Ré-authentification par mot de passe.
 * - Neutralisation des formules (injection CSV).
 * - Journal d'audit obligatoire.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();
gc_require_post();
require_csrf();

$reauth = gc_reauthenticate_current_admin((string)($_POST['current_password'] ?? ''));
if (!$reauth['ok']) {
    http_response_code(403);
    die("Export refusé : " . e($reauth['error']) . " Revenez en arrière et réessayez.");
}

function gc_csv_safe($value): string {
    $text = (string)$value;
    if ($text !== '' && preg_match('/^[=+\-@\t\r]/', ltrim($text, " \t")) === 1) {
        return "'" . $text;
    }
    return $text;
}

$pdo = get_admin_pdo();
$stmt = $pdo->query("SELECT id, dossier_num, firstname, lastname, email, phone, country, city, situation_pro, foyer, urgency, status, has_missing_doc, created_at FROM gc_candidatures ORDER BY id DESC");
$rows = $stmt->fetchAll();

if (!gc_audit('export_csv', 'gc_candidatures', ['rows' => count($rows)])) {
    http_response_code(503);
    die("Export impossible : le journal d'audit est indisponible. Contactez l'administrateur système.");
}

$filename = 'candidatures_acfe_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'ID', 'Numéro Dossier', 'Prénom', 'Nom', 'E-mail', 'Téléphone', 'Pays', 'Ville',
    'Situation Pro', 'Foyer', 'Degré Urgence', 'Statut', 'Doc Manquant', 'Date Dépôt'
], ';', '"', '\\');

foreach ($rows as $r) {
    fputcsv($output, [
        (int)$r['id'],
        gc_csv_safe($r['dossier_num']),
        gc_csv_safe($r['firstname']),
        gc_csv_safe($r['lastname']),
        gc_csv_safe($r['email']),
        gc_csv_safe($r['phone']),
        gc_csv_safe($r['country']),
        gc_csv_safe($r['city']),
        gc_csv_safe($r['situation_pro']),
        gc_csv_safe($r['foyer']),
        gc_csv_safe($r['urgency']),
        gc_csv_safe($r['status']),
        $r['has_missing_doc'] ? 'Oui' : 'Non',
        gc_csv_safe($r['created_at']),
    ], ';', '"', '\\');
}

fclose($output);
exit;
