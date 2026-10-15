<?php
/**
 * Téléchargement sécurisé des pièces justificatives.
 */
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();

$cand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type    = isset($_GET['type']) ? preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$_GET['type'])) : 'id';
$token   = $_GET['token'] ?? '';

if ($cand_id <= 0 || !in_array($type, ['id', 'situation'], true)) {
    http_response_code(400);
    die('Paramètres de téléchargement invalides.');
}

$secret = defined('GC_CSRF_SECRET') ? GC_CSRF_SECRET : '';
$expected = hash_hmac('sha256', "dl_{$cand_id}_{$type}", $secret);
if ($secret === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    die('Lien de téléchargement invalide ou expiré.');
}

$pdo = get_admin_pdo();
$stmt = $pdo->prepare("SELECT id_document, id_document_orig, situation_document, situation_document_orig FROM gc_candidatures WHERE id = ? LIMIT 1");
$stmt->execute([$cand_id]);
$cand = $stmt->fetch();

if (!$cand) {
    http_response_code(404);
    die('Dossier de candidature introuvable.');
}

$filename  = ($type === 'situation') ? $cand['situation_document'] : $cand['id_document'];
$orig_name = ($type === 'situation') ? $cand['situation_document_orig'] : $cand['id_document_orig'];

if (empty($filename)) {
    http_response_code(404);
    die('Aucun document n\'a été fourni pour cette catégorie.');
}

$storage_dir = rtrim(defined('GC_SECURE_STORAGE_PATH') ? GC_SECURE_STORAGE_PATH : '', DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR;
$full_path   = realpath($storage_dir . basename($filename));
$real_storage = realpath($storage_dir);

if ($full_path === false || $real_storage === false || strpos($full_path, $real_storage) !== 0 || !is_file($full_path)) {
    http_response_code(404);
    die('Fichier introuvable sur le serveur sécurisé.');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $full_path);
finfo_close($finfo);

if (!in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    $mime_type = 'application/octet-stream';
}

$clean_orig = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $orig_name ?: basename($filename));

gc_audit('download_document', [
    'candidature_id' => $cand_id,
    'type'           => $type,
    'filename'       => basename($filename),
]);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($full_path));
header('Content-Disposition: attachment; filename="' . $clean_orig . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($full_path);
exit;
