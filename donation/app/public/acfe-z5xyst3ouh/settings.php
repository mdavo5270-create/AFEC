<?php
/**
 * Paramètres de Sécurité Administrateur — ACFE Admin
 * Durci : ré-authentification, 2FA chiffrée côté serveur, fermeture des autres sessions.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/crypto.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();

$pdo = get_admin_pdo();
$user_id = (int)$_SESSION['admin_user_id'];

$notice = '';
$notice_type = '';

$stmt = $pdo->prepare("SELECT * FROM gc_admin_users WHERE id = ?");
$stmt->execute([$user_id]);
$admin_user = $stmt->fetch();
if (!$admin_user) {
    die('Utilisateur introuvable.');
}

$has_2fa = !empty($admin_user['totp_secret_enc'])
    || !empty($admin_user['two_factor_enabled'])
    || !empty($admin_user['totp_secret'])
    || !empty($admin_user['two_factor_secret']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pwd'])) {
    require_csrf();
    require_recent_auth(300);

    $old_pass  = (string)($_POST['old_password'] ?? '');
    $new_pass  = (string)($_POST['new_password'] ?? '');
    $conf_pass = (string)($_POST['confirm_password'] ?? '');

    if (!password_verify($old_pass, $admin_user['password_hash'])) {
        $notice = 'L\'ancien mot de passe est incorrect.';
        $notice_type = 'error';
    } elseif (strlen($new_pass) < 12) {
        $notice = 'Le nouveau mot de passe doit comporter au moins 12 caractères.';
        $notice_type = 'error';
    } elseif (!preg_match('/[A-Z]/', $new_pass) || !preg_match('/[a-z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
        $notice = 'Le mot de passe doit contenir majuscules, minuscules et chiffres.';
        $notice_type = 'error';
    } elseif ($new_pass !== $conf_pass) {
        $notice = 'Les nouveaux mots de passe ne correspondent pas.';
        $notice_type = 'error';
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare("UPDATE gc_admin_users SET password_hash = ? WHERE id = ?");
        $upd->execute([$new_hash, $user_id]);
        session_regenerate_id(true);
        $_SESSION['REAUTH_AT'] = time();
        gc_audit('password_changed', []);
        $notice = 'Votre mot de passe a été modifié avec succès. Les autres sessions ont été fermées.';
        $notice_type = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_2fa'])) {
    require_csrf();
    require_recent_auth(300);

    if (isset($_POST['disable_2fa'])) {
        $code = trim((string)($_POST['totp_code'] ?? ''));
        $secret = '';
        try {
            if (!empty($admin_user['totp_secret_enc'])) {
                $secret = gc_decrypt($admin_user['totp_secret_enc']);
            } elseif (!empty($admin_user['two_factor_secret'])) {
                $secret = (string)$admin_user['two_factor_secret'];
            } elseif (!empty($admin_user['totp_secret'])) {
                $secret = (string)$admin_user['totp_secret'];
            }
        } catch (Throwable $e) {
            $notice = 'Erreur de lecture du secret 2FA.';
            $notice_type = 'error';
            $secret = '';
        }
        if ($secret && verify_totp_code($secret, $code)) {
            $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 0, two_factor_secret = NULL, totp_secret = NULL, totp_secret_enc = NULL WHERE id = ?");
            $upd->execute([$user_id]);
            gc_audit('2fa_disabled', []);
            $notice = 'La double authentification a été désactivée.';
            $notice_type = 'success';
            $has_2fa = false;
            $admin_user['two_factor_enabled'] = 0;
        } else {
            $notice = 'Code 2FA incorrect. Désactivation refusée.';
            $notice_type = 'error';
        }
    } else {
        $secret = $_SESSION['pending_2fa_secret'] ?? '';
        $code   = trim((string)($_POST['totp_code'] ?? ''));
        if ($secret === '' || !verify_totp_code($secret, $code)) {
            $notice = 'Le code 2FA saisi est incorrect. L\'activation a échoué.';
            $notice_type = 'error';
        } else {
            $enc = gc_encrypt($secret);
            $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 1, totp_secret_enc = ?, two_factor_secret = NULL, totp_secret = NULL WHERE id = ?");
            $upd->execute([$enc, $user_id]);
            unset($_SESSION['pending_2fa_secret']);
            gc_audit('2fa_enabled', []);
            $notice = 'La double authentification (2FA) est désormais activée sur votre compte.';
            $notice_type = 'success';
            $has_2fa = true;
            $admin_user['two_factor_enabled'] = 1;
        }
    }
}

$new_2fa_secret = '';
if (!$has_2fa) {
    if (empty($_SESSION['pending_2fa_secret'])) {
        $_SESSION['pending_2fa_secret'] = generate_totp_secret(16);
    }
    $new_2fa_secret = $_SESSION['pending_2fa_secret'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Sécurité & Paramètres — ACFE Admin</title>
    <style>
        :root { --gc-navy: #0b1a2d; --gc-orange: #c05621; --gc-bg: #f4f6f9; --gc-card: #fff; --gc-border: #e2e8f0; --gc-text: #1a202c; --gc-muted: #718096; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--gc-bg); color: var(--gc-text); margin: 0; padding: 2rem; }
        .card { background: var(--gc-card); border: 1px solid var(--gc-border); border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; max-width: 560px; }
        h1 { font-size: 1.4rem; color: var(--gc-navy); margin-top: 0; }
        h2 { font-size: 1.1rem; margin: 0 0 1rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.9rem; }
        input[type=password], input[type=text] { width: 100%; padding: 0.55rem 0.75rem; border: 1px solid var(--gc-border); border-radius: 6px; margin-bottom: 0.9rem; font-size: 1rem; }
        button, .btn { background: var(--gc-navy); color: #fff; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer; font-size: 0.95rem; }
        button.danger { background: #c53030; }
        .notice { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .notice.success { background: #c6f6d5; color: #22543d; }
        .notice.error { background: #fed7d7; color: #742a2a; }
        .muted { color: var(--gc-muted); font-size: 0.85rem; }
        a { color: var(--gc-orange); }
        code { background: #edf2f7; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <p><a href="index.php">&larr; Retour à la liste</a></p>
    <h1>Sécurité & Paramètres</h1>

    <?php if ($notice): ?>
        <div class="notice <?= e($notice_type) ?>"><?= e($notice) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Changer le mot de passe</h2>
        <p class="muted">Minimum 12 caractères, avec majuscules, minuscules et chiffres. Une ré-authentification récente est exigée.</p>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action_pwd" value="1">
            <label for="old_password">Mot de passe actuel</label>
            <input type="password" id="old_password" name="old_password" required autocomplete="current-password">
            <label for="new_password">Nouveau mot de passe</label>
            <input type="password" id="new_password" name="new_password" required minlength="12" autocomplete="new-password">
            <label for="confirm_password">Confirmer le nouveau mot de passe</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="12" autocomplete="new-password">
            <button type="submit">Mettre à jour le mot de passe</button>
        </form>
    </div>

    <div class="card">
        <h2>Authentification à deux facteurs (2FA)</h2>
        <?php if ($has_2fa): ?>
            <p>La 2FA est <strong>activée</strong> sur votre compte.</p>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action_2fa" value="1">
                <input type="hidden" name="disable_2fa" value="1">
                <label for="totp_disable">Code 2FA actuel (pour confirmer la désactivation)</label>
                <input type="text" id="totp_disable" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
                <button type="submit" class="danger">Désactiver la 2FA</button>
            </form>
        <?php else: ?>
            <p>Scannez ce secret dans votre application d'authentification (Google Authenticator, Authy, etc.) :</p>
            <p><code><?= e($new_2fa_secret) ?></code></p>
            <p class="muted">Le secret est généré côté serveur et n'est jamais renvoyé par le navigateur.</p>
            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="action_2fa" value="1">
                <label for="totp_code">Code à 6 chiffres généré par l'application</label>
                <input type="text" id="totp_code" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code">
                <button type="submit">Activer la 2FA</button>
            </form>
        <?php endif; ?>
    </div>

    <p class="muted">Les actions sensibles exigent une connexion datant de moins de 5 minutes. Si vous êtes redirigé vers la page de connexion, reconnectez-vous puis réessayez.</p>
</body>
</html>
