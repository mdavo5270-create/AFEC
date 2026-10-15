<?php
/**
 * Export CSV Sécurisé — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';

check_admin_auth();
require_csrf();

$pdo = get_admin_pdo();
$stmt = $pdo->query("SELECT id, dossier_num, firstname, lastname, email, phone, country, city, situation_pro, foyer, urgency, status, has_missing_doc, created_at FROM gc_candidatures ORDER BY id DESC");
$rows = $stmt->fetchAll();

$filename = 'candidatures_acfe_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');

$output = fopen('php://output', 'w');
// BOM UTF-8 pour ouverture correcte dans Microsoft Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, [
    'ID',
    'Numéro Dossier',
    'Prénom',
    'Nom',
    'E-mail',
    'Téléphone',
    'Pays',
    'Ville',
    'Situation Pro',
    'Foyer',
    'Degré Urgence',
    'Statut',
    'Doc Manquant',
    'Date Dépôt'
], ';');

foreach ($rows as $r) {
    fputcsv($output, [
        $r['id'],
        $r['dossier_num'],
        $r['firstname'],
        $r['lastname'],
        $r['email'],
        $r['phone'],
        $r['country'],
        $r['city'],
        $r['situation_pro'],
        $r['foyer'],
        $r['urgency'],
        $r['status'],
        $r['has_missing_doc'] ? 'Oui' : 'Non',
        $r['created_at'],
    ], ';');
}

fclose($output);
exit;
