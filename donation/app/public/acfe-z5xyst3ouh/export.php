<?php
/**
 * Export CSV des candidatures — sécurisé contre l'injection de formules
 * et journalisé.
 */
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();
require_csrf();

$status = isset($_POST['status']) ? trim((string)$_POST['status']) : '';
$allowed_statuses = ['', 'recue', 'en_verification', 'en_analyse', 'liste_courte', 'retenue', 'contactee', 'injoignable', 'confirmee', 'payee', 'refusee'];
if (!in_array($status, $allowed_statuses, true)) {
    $status = '';
}

$pdo = get_admin_pdo();

$sql = "SELECT id, dossier_num, firstname, lastname, email, phone, city, country, status, created_at
        FROM gc_candidatures";
$params = [];
if ($status !== '') {
    $sql .= " WHERE status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY created_at DESC LIMIT 10000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function csv_safe(string $value): string {
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    if ($value !== '' && preg_match('/^[=+\-@\t\r]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

gc_audit('export_csv', [
    'status_filter' => $status ?: 'all',
    'row_count'     => count($rows),
]);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="candidatures_' . date('Y-m-d_His') . '.csv"');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', 'N° Dossier', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Ville', 'Pays', 'Statut', 'Date création'], ';');

foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        csv_safe((string)$r['dossier_num']),
        csv_safe((string)$r['firstname']),
        csv_safe((string)$r['lastname']),
        csv_safe((string)$r['email']),
        csv_safe((string)$r['phone']),
        csv_safe((string)$r['city']),
        csv_safe((string)$r['country']),
        csv_safe((string)$r['status']),
        $r['created_at'],
    ], ';');
}
fclose($out);
exit;
