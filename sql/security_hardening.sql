-- Migration sécurité ACFE — à exécuter une seule fois sur la base de production
-- Après rotation des mots de passe et secrets.

-- 1. Journal d'audit
CREATE TABLE IF NOT EXISTS gc_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(100) NULL,
    action VARCHAR(64) NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Colonne 2FA chiffrée
ALTER TABLE gc_admin_users
    ADD COLUMN IF NOT EXISTS totp_secret_enc TEXT NULL AFTER two_factor_secret;

-- 3. Contraintes d'unicité anti-doublons (si pas déjà présentes)
-- Attention : échoue s'il existe déjà des doublons ; nettoyez d'abord.
-- ALTER TABLE gc_candidatures ADD UNIQUE INDEX uq_phone (phone);
-- ALTER TABLE gc_candidatures ADD UNIQUE INDEX uq_email (email);

-- 4. Table anti-doublons téléphone (option recommandée)
CREATE TABLE IF NOT EXISTS gc_phones (
    phone VARCHAR(32) NOT NULL PRIMARY KEY,
    candidature_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_cand (candidature_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
