<?php
/**
 * Connexion PDO Sécurisée — Espace Administration ACFE
 * 
 * Utilise l'utilisateur MySQL acfe_admin avec les privilèges appropriés.
 * Strictement avec requêtes préparées PDO sans exception.
 */

// Charger la configuration sécurisée située hors de la racine web
$config_path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'secure_config' . DIRECTORY_SEPARATOR . 'app_config.php';
if (!file_exists($config_path)) {
    die("Erreur critique : Fichier de configuration sécurisé introuvable.");
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
            PDO::ATTR_EMULATE_PREPARES   => false, // Désactive l'émulation pour de vraies requêtes préparées
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];
        try {
            $pdo = new PDO($dsn, GC_DB_ADMIN_USER, GC_DB_ADMIN_PASS, $options);
        } catch (PDOException $e) {
            error_log("Erreur de connexion PDO Admin: " . $e->getMessage());
            die("Erreur de connexion sécurisée à la base de données. Veuillez réessayer ultérieurement.");
        }
    }
    return $pdo;
}
