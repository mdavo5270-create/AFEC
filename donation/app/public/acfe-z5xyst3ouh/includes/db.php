<?php
/**
 * Connexion PDO Sécurisée — Espace Administration ACFE
 *
 * Utilise l'utilisateur MySQL acfe_admin avec les privilèges appropriés.
 * Strictement avec requêtes préparées PDO sans exception.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

// Charger la configuration sécurisée située hors de la racine web
$config_path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
if (!file_exists($config_path)) {
    error_log('Fichier de configuration sécurisé introuvable : ' . $config_path);
    http_response_code(500);
    die("Erreur critique : configuration indisponible.");
}
require_once $config_path;

function get_admin_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            GC_DB_HOST,
            GC_DB_PORT,
            GC_DB_NAME,
            GC_DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        if (defined('GC_DB_SSL_CA') && GC_DB_SSL_CA !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = GC_DB_SSL_CA;
        }
        try {
            $pdo = new PDO($dsn, GC_DB_ADMIN_USER, GC_DB_ADMIN_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erreur de connexion PDO Admin: " . $e->getMessage());
            http_response_code(503);
            die("Erreur de connexion sécurisée à la base de données. Veuillez réessayer ultérieurement.");
        }
    }
    return $pdo;
}
