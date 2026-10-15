# Correctifs de sécurité appliqués (session 15/10/2026)

## Fichiers modifiés / créés (admin custom)

| Fichier | Changements |
|---------|-------------|
| `includes/security.php` | IP fiable (REMOTE_ADDR + proxies déclarés), HTTPS canonique, HSTS, CSP stricte, `require_recent_auth()` |
| `includes/crypto.php` | **Nouveau** — chiffrement sodium/OpenSSL des secrets 2FA |
| `includes/audit.php` | **Nouveau** — journal d'audit des actions sensibles |
| `includes/auth.php` | Rate-limit IP + username, 2FA chiffrée, audit login |
| `includes/csrf.php` | CSRF uniquement via POST (plus jamais GET) |
| `settings.php` | Ré-auth obligatoire, secret 2FA généré côté serveur + chiffré, désactivation protégée |
| `export.php` | Protection injection formules CSV, BOM UTF-8, journalisation, CSRF POST |
| `download.php` | Content-Disposition: attachment, audit, chemin strict |

## Plugin WordPress (`gestion-candidatures`)

| Fichier | Changements |
|---------|-------------|
| `includes/form.php` | Helpers `gc_client_ip`, `gc_rate_limited`, `gc_clip` ; téléchargement en `attachment` |

## Actions manuelles obligatoires (production)

1. **Exécuter** `sql/security_hardening.sql` sur la base.
2. **Configurer** dans `secure_config/app_config.php` :
   - `GC_CANONICAL_HOST` (ex. `www.acfe-solidarite.org`)
   - `GC_TRUSTED_PROXIES` (IP du reverse-proxy si présent)
   - `GC_CSRF_SECRET` long et aléatoire (déjà fait si secrets rotatés)
3. **Forcer 2FA** pour tous les comptes admin (via settings).
4. **Activer la restriction IP** (`GC_IP_RESTRICTION_ENABLED = true`) une fois les IPs connues.
5. **Ne plus collecter les pièces d'identité** dès le dépôt public — les demander uniquement aux retenus (changement métier à planifier).
6. **Purger l'historique Git** des anciens secrets (BFG / git filter-repo) si ce n'est pas déjà fait.
7. **Configurer SPF / DKIM / DMARC** sur le domaine d'envoi d'e-mails.
8. **Supprimer** `readme.html`, `license.txt`, thèmes inutilisés et la « Sample Page » WordPress.

## Points encore ouverts (non codés ici)

- Page de suivi : passer en POST + identifiant aléatoire long + rate-limit dédié
- Contraintes UNIQUE téléphone/email en base (migration données existantes)
- Cloudflare Turnstile
- Analyse antivirus des uploads
- Mentions légales / responsable de traitement RGPD

## Correctifs session 2 (suite)

- Suivi de dossier : formulaire en **POST**, nonce, rate-limit 8/h, critères stricts (n° dossier + téléphone), message d'échec unique (anti-énumération).
- Numéro de dossier : entropie ~16M valeurs/an (hex).
- Anti-doublons atomiques via table `gc_phones` + transaction PDO.
- Longueurs de champs bornées (`gc_clip`).
- Messages d'erreur génériques (plus de fuite téléphone/email).
