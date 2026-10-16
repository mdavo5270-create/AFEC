<?php
/**
 * Protection CSRF — Espace Administration ACFE
 *
 * Jeton de session généré cryptographiquement, accepté uniquement dans le corps d'une requête POST
 * (ou l'en-tête X-CSRF-Token) : jamais dans l'URL, où il fuirait dans les journaux et les en-têtes Referer.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): bool {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || $token === '' || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void {
    if (!verify_csrf()) {
        http_response_code(403);
        die("Erreur de sécurité : Jeton de validation CSRF invalide ou expiré. Veuillez recharger la page.");
    }
}
