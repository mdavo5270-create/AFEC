<?php
/**
 * Streaming Sécurisé des Pièces Justificatives — ACFE Admin
 *
 * Accès réservé aux administrateurs connectés (2FA activé).
 * Les fichiers sont stockés hors de la racine web (secure_storage).
 * Le lien est signé, lié à l'administrateur qui l'a généré et valable 10 minutes.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();

$cand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type    = isset($_GET['type']) ? sanitize_key((string)$_GET['type']) : 'id';
$expires = isset($_GET['exp']) ? (int)$_GET['exp'] : 0;
$token   = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';

if ($cand_id <= 0 || !in_array($type, ['id', 'situation'], true)) {
    http_response_code(400);
    die("Paramètres de téléchargement invalides.");
}

if (!gc_verify_download_token($cand_id, $type, $expires, $token)) {
    http_response_code(403);
    die("Lien de téléchargement invalide ou expiré. Rouvrez la fiche du dossier pour en générer un nouveau.");
}

$pdo = get_admin_pdo();
$stmt = $pdo->prepare("SELECT id_document, id_document_orig, situation_document, situation_document_orig FROM gc_candidatures WHERE id = ? LIMIT 1");
$stmt->execute([$cand_id]);
$cand = $stmt->fetch();

if (!$cand) {
    http_response_code(404);
    die("Dossier de candidature introuvable.");
}

$filename = ($type === 'situation') ? $cand['situation_document'] : $cand['id_document'];
$orig_name = ($type === 'situation') ? $cand['situation_document_orig'] : $cand['id_document_orig'];

if (empty($filename)) {
    http_response_code(404);
    die("Aucun document n'a été fourni pour cette catégorie.");
}

$storage_dir  = rtrim(GC_SECURE_STORAGE_PATH, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR;
$storage_real = realpath($storage_dir);
$full_path    = realpath($storage_dir . basename((string)$filename));

if (
    $storage_real === false
    || $full_path === false
    || strpos($full_path, $storage_real . DIRECTORY_SEPARATOR) !== 0
    || !is_file($full_path)
) {
    http_response_code(404);
    die("Fichier introuvable sur le serveur sécurisé.");
}

$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $full_path);
finfo_close($finfo);

if (!in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    http_response_code(415);
    die("Type de fichier non pris en charge.");
}

if (!gc_audit('download_document', "candidature:{$cand_id}", ['type' => $type])) {
    http_response_code(503);
    die("Téléchargement impossible : le journal d'audit est indisponible.");
}

$clean_orig_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $orig_name ?: basename((string)$filename));

while (ob_get_level() > 0) {
    ob_end_clean();
}
header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($full_path));
header("Content-Disposition: attachment; filename=\"" . $clean_orig_name . "\"");
header("X-Content-Type-Options: nosniff");
header("Content-Security-Policy: default-src 'none'; sandbox");
header("Cache-Control: private, no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

readfile($full_path);
exit;

function sanitize_key(string $key): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
}
