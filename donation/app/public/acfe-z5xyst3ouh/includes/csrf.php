<?php
/**
 * Protection CSRF — Espace Administration ACFE
 * 
 * Génération cryptographique et validation de jetons à usage unique ou de session.
 */

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
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token'])) {
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
