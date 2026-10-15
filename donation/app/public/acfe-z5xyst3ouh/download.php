<?php
/**
 * Streaming Sécurisé des Pièces Justificatives — ACFE Admin
 * 
 * Accès réservé aux administrateurs connectés.
 * Les fichiers sont stockés hors de la racine web (secure_storage).
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';

check_admin_auth();

$cand_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type    = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'id';
$token   = $_GET['token'] ?? '';

if ($cand_id <= 0 || !in_array($type, ['id', 'situation'], true)) {
    http_response_code(400);
    die("Paramètres de téléchargement invalides.");
}

// Validation du jeton de sécurité HMAC
$expected_token = hash_hmac('sha256', "dl_{$cand_id}_{$type}", GC_CSRF_SECRET);
if (!hash_equals($expected_token, $token)) {
    http_response_code(403);
    die("Lien de téléchargement invalide ou expiré.");
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

// Résolution et vérification stricte du chemin (anti directory traversal)
$storage_dir = rtrim(GC_SECURE_STORAGE_PATH, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR;
$full_path   = realpath($storage_dir . basename($filename));

if ($full_path === false || strpos($full_path, realpath($storage_dir)) !== 0 || !file_exists($full_path)) {
    http_response_code(404);
    die("Fichier introuvable sur le serveur sécurisé.");
}

// Détection MIME réelle
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $full_path);
finfo_close($finfo);

if (!in_array($mime_type, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
    $mime_type = 'application/octet-stream';
}

$clean_orig_name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $orig_name ?: basename($filename));

// Envoi des en-têtes sécurisés
header("Content-Type: " . $mime_type);
header("Content-Length: " . filesize($full_path));
header("Content-Disposition: inline; filename=\"" . $clean_orig_name . "\"");
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

readfile($full_path);
exit;

function sanitize_key($key) {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
}
