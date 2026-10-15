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

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT * FROM gc_admin_users WHERE id = ?");
$stmt->execute([$user_id]);
$admin_user = $stmt->fetch();

if (!$admin_user) {
    die("Utilisateur introuvable.");
}

// 1. Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_pwd'])) {
    require_csrf();

    $old_pass  = (string)($_POST['old_password'] ?? '');
    $new_pass  = (string)($_POST['new_password'] ?? '');
    $conf_pass = (string)($_POST['confirm_password'] ?? '');

    if (!password_verify($old_pass, $admin_user['password_hash'])) {
        $notice = "L'ancien mot de passe est incorrect.";
        $notice_type = "error";
    } elseif (strlen($new_pass) < 10) {
        $notice = "Le nouveau mot de passe doit comporter au moins 10 caractères.";
        $notice_type = "error";
    } elseif ($new_pass !== $conf_pass) {
        $notice = "Les nouveaux mots de passe ne correspondent pas.";
        $notice_type = "error";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $upd = $pdo->prepare("UPDATE gc_admin_users SET password_hash = ? WHERE id = ?");
        $upd->execute([$new_hash, $user_id]);
        $notice = "Votre mot de passe a été modifié avec succès.";
        $notice_type = "success";
    }
}

// 2. Traitement 2FA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_2fa'])) {
    require_csrf();

    $totp_code = trim((string)($_POST['totp_code'] ?? ''));
    $secret    = trim((string)($_POST['totp_secret'] ?? ''));

    if (isset($_POST['disable_2fa'])) {
        $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?");
        $upd->execute([$user_id]);
        $notice = "La double authentification a été désactivée.";
        $notice_type = "success";
        $admin_user['two_factor_enabled'] = 0;
    } else {
        if (verify_totp_code($secret, $totp_code)) {
            $upd = $pdo->prepare("UPDATE gc_admin_users SET two_factor_enabled = 1, two_factor_secret = ? WHERE id = ?");
            $upd->execute([$secret, $user_id]);
            $notice = "La double authentification (2FA) est désormais activée sur votre compte.";
            $notice_type = "success";
            $admin_user['two_factor_enabled'] = 1;
        } else {
            $notice = "Le code 2FA saisi est incorrect. L'activation a échoué.";
            $notice_type = "error";
        }
    }
}

// Générer un nouveau secret 2FA pour la configuration si non actif
$new_2fa_secret = '';
if (empty($admin_user['two_factor_enabled'])) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    for ($i = 0; $i < 16; $i++) {
        $new_2fa_secret .= $chars[random_int(0, 31)];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Sécurité &amp; Paramètres — ACFE Admin</title>
    <style>
        :root {
            --gc-navy: #0b1a2d;
            --gc-orange: #c05621;
            --gc-slate: #1e293b;
            --gc-border: #e2e8f0;
            --gc-bg: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--gc-bg); color: var(--gc-slate); line-height: 1.6; }
        .admin-nav { background: var(--gc-navy); color: #ffffff; padding: 14px 28px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-size: 16px; font-weight: 800; color: #ffffff; text-decoration: none; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; margin-left: 18px; font-weight: 600; }
        .nav-links a:hover { color: #ffffff; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .card { background: #ffffff; border-radius: 12px; border: 1px solid var(--gc-border); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 24px; }
        .card-title { font-size: 18px; font-weight: 800; color: var(--gc-navy); margin-bottom: 6px; }
        .card-desc { font-size: 13.5px; color: #64748b; margin-bottom: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13.5px; font-weight: 700; color: #334155; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1.5px solid var(--gc-border); border-radius: 8px; font-size: 14.5px; }
        .btn-submit { padding: 11px 22px; background: var(--gc-orange); color: #ffffff; border: none; border-radius: 6px; font-weight: 700; font-size: 14.5px; cursor: pointer; }
        .btn-submit:hover { background: #9c4215; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .secret-box { background: #f1f5f9; padding: 12px; border-radius: 6px; font-family: monospace; font-size: 18px; font-weight: 700; letter-spacing: 2px; color: var(--gc-navy); margin: 10px 0; display: inline-block; }
    </style>
</head>
<body>

<nav class="admin-nav">
    <a href="index.php" class="nav-brand">ACFE &bull; Espace Administration</a>
    <div class="nav-links">
        <a href="index.php">Dossiers</a>
        <a href="settings.php">Sécurité</a>
        <a href="logout.php">Déconnexion (<?php echo e($_SESSION['admin_username']); ?>)</a>
    </div>
</nav>

<div class="container">
    <h1 style="font-size:24px; font-weight:800; color:var(--gc-navy); margin-bottom:24px;">Paramètres de Sécurité</h1>

    <?php if ($notice !== ''): ?>
        <div class="alert-<?php echo e($notice_type); ?>"><?php echo e($notice); ?></div>
    <?php endif; ?>

    <!-- 1. Mot de passe -->
    <div class="card">
        <h2 class="card-title">Changer le mot de passe administrateur</h2>
        <p class="card-desc">Utilisez un mot de passe robuste d'au moins 10 caractères, mêlant majuscules, chiffres et symboles.</p>

        <form method="post" action="settings.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action_pwd" value="1">

            <div class="form-group">
                <label class="form-label" for="old_password">Mot de passe actuel</label>
                <input type="password" name="old_password" id="old_password" class="form-control" required autocomplete="current-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Nouveau mot de passe</label>
                <input type="password" name="new_password" id="new_password" class="form-control" required minlength="10" autocomplete="new-password">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirmer le nouveau mot de passe</label>
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="10" autocomplete="new-password">
            </div>

            <button type="submit" class="btn-submit">Mettre à jour le mot de passe &rarr;</button>
        </form>
    </div>

    <!-- 2. Double Authentification (2FA) -->
    <div class="card">
        <h2 class="card-title">Authentification à deux facteurs (2FA TOTP)</h2>
        <p class="card-desc">Ajoute une couche de sécurité supplémentaire. Compatible avec Google Authenticator, Microsoft Authenticator ou Bitwarden.</p>

        <?php if (!empty($admin_user['two_factor_enabled'])): ?>
            <div style="background:#ecfdf5; border:1px solid #a7f3d0; padding:14px; border-radius:8px; color:#065f46; font-weight:700; margin-bottom:16px;">
                La double authentification est actuellement ACTIVÉE sur votre compte.
            </div>
            <form method="post" action="settings.php" onsubmit="return confirm('Êtes-vous sûr de vouloir désactiver la protection 2FA ?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_2fa" value="1">
                <input type="hidden" name="disable_2fa" value="1">
                <button type="submit" class="btn-submit btn-danger">Désactiver le 2FA</button>
            </form>
        <?php else: ?>
            <p style="font-size:14px; margin-bottom:10px;">Pour activer le 2FA, saisissez cette clé secrète dans votre application d'authentification :</p>
            <div class="secret-box"><?php echo e($new_2fa_secret); ?></div>

            <form method="post" action="settings.php" style="margin-top:16px;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action_2fa" value="1">
                <input type="hidden" name="totp_secret" value="<?php echo e($new_2fa_secret); ?>">

                <div class="form-group">
                    <label class="form-label" for="totp_code">Code de confirmation à 6 chiffres affiché par votre application</label>
                    <input type="text" name="totp_code" id="totp_code" class="form-control" maxlength="6" pattern="[0-9]{6}" placeholder="Ex. 123456" required style="max-width:240px;">
                </div>

                <button type="submit" class="btn-submit">Confirmer et Activer le 2FA &rarr;</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- 3. Restriction d'Adresse IP -->
    <div class="card">
        <h2 class="card-title">Contrôle d'accès par Adresse IP</h2>
        <p class="card-desc">Votre adresse IP actuelle détectée : <strong style="font-family:monospace; color:var(--gc-navy);"><?php echo e(get_client_ip()); ?></strong></p>

        <?php if (GC_IP_RESTRICTION_ENABLED): ?>
            <div style="background:#ecfdf5; border:1px solid #a7f3d0; padding:12px; border-radius:8px; color:#065f46; font-weight:700;">
                Filtrage IP : ACTIF. Seules les adresses listées dans <code>app_config.php</code> ont l'autorisation d'accéder au portail.
            </div>
        <?php else: ?>
            <div style="background:#fffbeb; border:1px solid #fde68a; padding:12px; border-radius:8px; color:#92400e;">
                Filtrage IP : INACTIF (Accessible depuis toutes les adresses IP).<br>
                Pour l'activer, modifiez <code>GC_IP_RESTRICTION_ENABLED = true</code> dans le fichier <code>secure_config/app_config.php</code>.
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
