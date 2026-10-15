<?php
/**
 * Page de Connexion Sécurisée — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

// Si déjà authentifié, redirection vers le tableau de bord
if (!empty($_SESSION['admin_user_id'])) {
    header("Location: index.php");
    exit;
}

$error_msg   = '';
$require_2fa = false;
$username    = '';

if (isset($_SESSION['expired_notice'])) {
    $error_msg = $_SESSION['expired_notice'];
    unset($_SESSION['expired_notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $code_2fa = !empty($_POST['code_2fa']) ? trim((string)$_POST['code_2fa']) : null;

    if ($username === '' || $password === '') {
        $error_msg = "Veuillez renseigner votre nom d'utilisateur et votre mot de passe.";
    } else {
        $result = attempt_login($username, $password, $code_2fa);
        if ($result['success']) {
            header("Location: index.php");
            exit;
        } else {
            $error_msg = $result['error'];
            if (!empty($result['require_2fa'])) {
                $require_2fa = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Connexion Sécurisée — ACFE Administration</title>
    <style>
        :root {
            --gc-navy: #0b1a2d;
            --gc-navy-dark: #071220;
            --gc-orange: #c05621;
            --gc-orange-hover: #9c4215;
            --gc-slate: #1e293b;
            --gc-border: #e2e8f0;
            --gc-bg: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--gc-navy-dark) 0%, var(--gc-navy) 100%);
            color: var(--gc-slate);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .login-card {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 14px;
            padding: 40px 36px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--gc-navy);
            letter-spacing: -0.3px;
            margin-bottom: 6px;
        }
        .login-subtitle {
            font-size: 13.5px;
            color: #64748b;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            margin-bottom: 22px;
            line-height: 1.5;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-size: 13.5px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 7px;
        }
        .form-control {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--gc-border);
            border-radius: 8px;
            font-size: 15px;
            color: #0f172a;
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: var(--gc-orange);
            box-shadow: 0 0 0 3px rgba(192, 86, 33, 0.2);
        }
        .btn-submit {
            width: 100%;
            padding: 13px;
            background: var(--gc-orange);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-submit:hover {
            background: var(--gc-orange-hover);
        }
        .login-footer {
            margin-top: 28px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            padding-top: 18px;
        }
        .security-badge {
            display: inline-block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #059669;
            background: #ecfdf5;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 700;
            margin-top: 6px;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <h1 class="login-title">ACFE &bull; Administration</h1>
        <p class="login-subtitle">Gestion sécurisée des candidatures</p>
        <span class="security-badge">Espace Privé &bull; Accès Restreint</span>
    </div>

    <?php if ($error_msg !== ''): ?>
        <div class="alert-error" role="alert">
            <?php echo e($error_msg); ?>
        </div>
    <?php endif; ?>

    <form method="post" action="login.php" autocomplete="off">
        <?php echo csrf_field(); ?>

        <div class="form-group">
            <label class="form-label" for="username">Identifiant Administrateur</label>
            <input type="text" name="username" id="username" class="form-control" value="<?php echo e($username); ?>" required autofocus autocomplete="username">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Mot de passe</label>
            <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
        </div>

        <?php if ($require_2fa): ?>
            <div class="form-group">
                <label class="form-label" for="code_2fa">Code Authentificateur 2FA (6 chiffres)</label>
                <input type="text" name="code_2fa" id="code_2fa" class="form-control" maxlength="6" pattern="[0-9]{6}" placeholder="123456" autofocus>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit">
            Se connecter en toute sécurité &rarr;
        </button>
    </form>

    <div class="login-footer">
        Connexion chiffrée &bull; Sessions éphémères &bull; Journalisation des accès
    </div>
</div>

</body>
</html>
