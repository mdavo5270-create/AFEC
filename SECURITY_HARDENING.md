# Durcissement sécurité ACFE — patch complet appliqué

Correctifs du fichier `afec-correctifs-securite.patch` intégrés sur `main`.

## Admin (`acfe-z5xyst3ouh/`)

| Module | Statut |
|--------|--------|
| `includes/security.php` | IP fiable, HTTPS, HSTS, CSP, COOP/CORP, sessions idle+absolues, `session_epoch`, 2FA obligatoire |
| `includes/auth.php` | `is_login_locked` IP+user, hash factice, 2FA chiffrée, `gc_reauthenticate_current_admin` |
| `includes/crypto.php` | sodium secretbox, jetons download signés 10 min |
| `includes/audit.php` | journal actions sensibles |
| `includes/csrf.php` | POST uniquement |
| `includes/db.php` | SSL optionnel, erreurs propres |
| `download.php` | token+exp+admin, MIME strict, audit obligatoire |
| `export.php` | POST+CSRF+ré-auth+CSV safe+audit |
| `.htaccess` | Apache 2.4, headers alignés |

## Plugin public

Déjà en place sur `main` (structure découpée) :
- suivi POST + rate-limit + anti-énumération
- anti-doublons `gc_phones` + transaction
- `gc_clip`, `gc_rate_limited`, `gc_client_ip`

## Production (hors dépôt)

1. Exécuter `sql/security_hardening.sql`
2. Configurer `secure_config/app_config.php` (`GC_CANONICAL_HOST`, `GC_TRUSTED_PROXIES`, secrets, optionnel `GC_DATA_KEY`)
3. Activer 2FA admin
4. SPF / DKIM / DMARC
