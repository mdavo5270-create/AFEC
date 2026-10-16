<?php
/**
 * Paramètres de Sécurité Administrateur — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

check_admin_auth();

$pdo = get_admin_pdo();
$user_id = $_SESSION['admin_user_id'];

$notice = '';
$notice_type = '';

$stmt = $pdo->prepare("SELECT * FROM gc_admin_users WHERE id = ?");
$stmt->execute([$user_id]);
$admin_user = $stmt->fetch();

if (!$admin_user) {
    die("Utilisateur introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pwd'])) {
    require_csrf();

    $old_pass  = (string)($_POST['old_password'] ?? '');
    $new_pass  = (string)($_POST['new_password'] ?? '');
    $conf_pass = (string)($_POST['confirm_password'] ?? '');

    $reauth = gc_reauthenticate_current_admin($old_pass);

    if (!$reauth['ok']) {
        $notice = ($reauth['error'] === 'Mot de passe incorrect.') ? "L'ancien mot de passe est incorrect." : $reauth['error'];
        $notice_type = "error";
    } elseif (mb_strlen($new_pass) < 12) {
        $notice = "Le nouveau mot de passe doit comporter au moins 12 caractères.";
        $notice_type = "error";
    } elseif (strlen($new_pass) > 72) {
        $notice = "Le nouveau mot de passe ne doit pas dépasser 72 octets.";
        $notice_type = "error";
    } elseif ($new_pass === $old_pass) {
        $notice = "Le nouveau mot de passe doit être différent de l'ancien.";
        $notice_type = "error";
    } elseif (stripos($new_pass, (string)$admin_user['username']) !== false) {
        $notice = "Le mot de passe ne doit pas contenir votre nom d'utilisateur.";
        $notice_type = "error";
    } elseif ($new_pass !== $conf_pass) {
        $notice = "Les nouveaux mots de passe ne correspondent pas.";
        $notice_type = "error";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare("UPDATE gc_admin_users SET password_hash = ? WHERE id = ?");
        $upd->execute([$new_hash, $user_id]);
        session_regenerate_id(true);
        gc_invalidate_other_sessions($pdo, (int)$user_id);
        $notice = "Votre mot de passe a été modifié avec succès. Les autres sessions ouvertes ont été fermées.";
        $notice_type = "success";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_2fa'])) {
    require_csrf();

    $totp_code = trim((string)($_POST['totp_code'] ?? ''));
    $password  = (string)($_POST['current_password'] ?? '');

    $reauth = gc_reauthenticate_current_admin($password);

    if (!$reauth['ok']) {
        $notice = $reauth['error'];
        $notice_type = "error";
    } elseif (isset($_POST['disable_2fa'])) {
        $current_secret = !empty($admin_user['two_factor_secret']) ? gc_decrypt_secret((string)$admin_user['two_factor_secret']) : null;
        if ($current_secret === null || !verify_totp_code($current_secret, $totp_code)) {
            record_login_attempt(get_client_ip(), (string)$admin_user['username'], false);
            $notice = "Le code 2FA saisi est incorrect. La désactivation a échoué.";
            $notice_type = "error";
        } else {
            $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
            $upd->execute([$user_id]);
            gc_invalidate_other_sessions($pdo, (int)$user_id);
            $_SESSION['must_setup_2fa'] = true;
            $notice = "La double authentification a été désactivée. Elle est obligatoire : veuillez la réactiver ci-dessous.";
            $notice_type = "success";
            $admin_user['two_factor_enabled'] = 0;
            $admin_user['two_factor_secret']  = null;
        }
    } else {
        $secret = (string)($_SESSION['pending_2fa_secret'] ?? '');
        if ($secret !== '' && verify_totp_code($secret, $totp_code)) {
            $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
            $upd->execute([gc_encrypt_secret($secret), $user_id]);
            unset($_SESSION['pending_2fa_secret'], $_SESSION['must_setup_2fa']);
            session_regenerate_id(true);
            gc_invalidate_other_sessions($pdo, (int)$user_id);
            $notice = "La double authentification (2FA) est désormais activée sur votre compte.";
            $notice_type = "success";
            $admin_user['two_factor_enabled'] = 1;
        } else {
            record_login_attempt(get_client_ip(), (string)$admin_user['username'], false);
            $notice = "Le code 2FA saisi est incorrect. L'activation a échoué.";
            $notice_type = "error";
        }
    }
}

$new_2fa_secret = '';
if (empty($admin_user['two_factor_enabled'])) {
    if (empty($_SESSION['pending_2fa_secret'])) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $generated = '';
        for ($i = 0; $i < 32; $i++) {
            $generated .= $chars[random_int(0, 31)];
        }
        $_SESSION['pending_2fa_secret'] = $generated;
    }
    $new_2fa_secret = (string)$_SESSION['pending_2fa_secret'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Paramètres de Sécurité — ACFE Admin</title>
    <style>
        :root { --gc-navy:#0f172a; --gc-orange:#ea580c; --gc-bg:#f8fafc; --gc-border:#e2e8f0; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:system-ui,-apple-system,sans-serif; background:var(--gc-bg); color:#0f172a; }
        .topbar { background:var(--gc-navy); color:#fff; padding:14px 24px; display:flex; justify-content:space-between; align-items:center; }
        .topbar a { color:#fdba74; text-decoration:none; font-weight:600; margin-left:16px; }
        .container { max-width:720px; margin:24px auto; padding:0 16px; }
        .card { background:#fff; border:1px solid var(--gc-border); border-radius:10px; padding:20px; margin-bottom:16px; }
        .card-title { font-size:16px; font-weight:800; margin:0 0 8px; color:var(--gc-navy); }
        .card-desc { font-size:13px; color:#64748b; margin:0 0 14px; }
        .form-group { margin-bottom:12px; }
        .form-label { display:block; font-size:13px; font-weight:700; margin-bottom:4px; }
        .form-control { width:100%; max-width:360px; padding:9px 12px; border:1px solid var(--gc-border); border-radius:6px; font-size:14px; }
        .btn-submit { padding:10px 16px; background:var(--gc-navy); color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer; }
        .btn-danger { background:#dc2626; }
        .alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px; border-radius:8px; margin-bottom:14px; font-weight:600; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:14px; font-weight:600; }
        .secret-box { font-family:ui-monospace,monospace; background:#f1f5f9; padding:12px; border-radius:8px; letter-spacing:1px; font-weight:700; word-break:break-all; }
    </style>
</head>
<body>
<div class="topbar">
    <div><strong>ACFE Admin</strong> — Sécurité</div>
    <div>
        <a href="index.php">Dossiers</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</div>
<div class="container">
    <h1 style="font-size:24px; font-weight:800; color:var(--gc-navy); margin-bottom:24px;">Paramètres de Sécurité</h1>

    <?php if (!empty($_SESSION['must_setup_2fa'])): ?>
        <div class="alert-error">Action requise : activez la double authentification (2FA) pour accéder aux dossiers des candidats.</div>
    <?php endif; ?>

    <?php if ($notice !== ''): ?>
        <div class="alert-<?php echo e($notice_type); ?>"><?php echo e($notice); ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 class="card-title">Changer le mot de passe administrateur</h2>
        <p class="card-desc">Utilisez un mot de passe robuste d'au moins 12 caractères (72 octets maximum), unique pour ce service.</p>
        <form method="post" action="settings.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_pwd" value="1">
            <div class="form-group">
                <label class="form-label" for="old_password">Mot de passe actuel</label>
                <input type="password" name="old_password" id="old_password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirmer le nouveau mot de passe</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="12" maxlength="72" autocomplete="new-password">
            </div>
            <button type="submit" class="btn-submit">Mettre à jour le mot de passe &rarr;</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Double authentification (2FA)</h2>
        <?php if (!empty($admin_user['two_factor_enabled'])): ?>
            <div style="background:#ecfdf5; border:1px solid #a7f3d0; padding:14px; border-radius:8px; color:#065f46; font-weight:700; margin-bottom:16px;">
                La double authentification est actuellement ACTIVÉE sur votre compte.
            </div>
            <p class="card-desc">Pour réinitialiser le 2FA, désactivez puis réactivez-le : mot de passe actuel et code valide requis.</p>
            <form method="post" action="settings.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_2fa" value="1">
                <input type="hidden" name="disable_2fa" value="1">
                <div class="form-group">
                    <label class="form-label" for="current_password_off">Mot de passe actuel</label>
                    <input type="password" name="current_password" id="current_password_off" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="totp_code_off">Code 2FA actuel à 6 chiffres</label>
                    <input type="text" name="totp_code" id="totp_code_off" class="form-control" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button type="submit" class="btn-submit btn-danger">Désactiver le 2FA</button>
            </form>
        <?php else: ?>
            <p style="font-size:14px; margin-bottom:10px;">Ajoutez ce compte dans votre application d'authentification (type temps, 6 chiffres, 30 s) :</p>
            <p style="font-size:13px; color:#64748b; margin-bottom:6px;">Compte : <strong><?php echo e($admin_user['username']); ?></strong> &bull; Émetteur : <strong>ACFE</strong></p>
            <div class="secret-box"><?php echo e(trim(chunk_split($new_2fa_secret, 4, ' '))); ?></div>
            <form method="post" action="settings.php" style="margin-top:16px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_2fa" value="1">
                <div class="form-group">
                    <label class="form-label" for="current_password_on">Mot de passe actuel</label>
                    <input type="password" name="current_password" id="current_password_on" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="totp_code">Code de confirmation à 6 chiffres</label>
                    <input type="text" name="totp_code" id="totp_code" class="form-control" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required>
                </div>
                <button type="submit" class="btn-submit">Confirmer et Activer le 2FA &rarr;</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
