# Durcissement sécurité ACFE — terminé

Session de sécurisation appliquée sur `main` (15/10/2026).

## Admin custom (`donation/.../acfe-z5xyst3ouh/`)

| Fichier | Rôle |
|---------|------|
| `includes/security.php` | IP fiable, HTTPS canonique, HSTS, CSP, `require_recent_auth()` |
| `includes/crypto.php` | Chiffrement sodium/OpenSSL des secrets 2FA |
| `includes/audit.php` | Journal d'audit des actions sensibles |
| `includes/auth.php` | Rate-limit IP + username, 2FA chiffrée, audit login |
| `includes/csrf.php` | CSRF POST uniquement |
| `settings.php` | Ré-auth, 2FA serveur + chiffrée |
| `export.php` | Anti injection formules CSV, audit, CSRF POST |
| `download.php` | Téléchargements sécurisés + audit |

## Plugin public (`gestion-candidatures/`)

| Fichier | Rôle |
|---------|------|
| `includes/form.php` | Helpers `gc_client_ip`, `gc_rate_limited`, `gc_clip` |
| `includes/form-core.php` | Shortcodes, assets, routeur |
| `includes/form-handlers.php` | Suivi POST, upload privé, download |
| `includes/form-submit.php` | Soumission : clip, anti-doublons atomiques (`gc_phones`), insert |
| `includes/form-pages2.php` / `form-pages2b.php` / `form-pages3.php` | Pages publiques |

## Migration SQL

Exécuter une fois en production : `sql/security_hardening.sql`

- table `gc_audit_log`
- colonne `totp_secret_enc`
- table `gc_phones` (anti-doublons atomiques)

## Checklist prod (hors code)

1. Configurer `secure_config/app_config.php` (`GC_CANONICAL_HOST`, `GC_TRUSTED_PROXIES`, secrets)
2. Activer 2FA admin + restriction IP si possible
3. SPF / DKIM / DMARC
4. Supprimer thèmes/plugins inutiles, `readme.html`, Sample Page
5. Si d’anciens secrets ont été dans l’historique Git : purge (BFG / filter-repo) + rotation

## .gitignore

Exclut notamment : `wp-config.php`, `secure_config/`, `local-xdebuginfo.php`, `.env*`, uploads privés.
