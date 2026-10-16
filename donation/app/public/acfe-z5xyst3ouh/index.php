<?php
/**
 * Tableau de bord — Liste des candidatures ACFE Admin
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';

check_admin_auth();

$pdo = get_admin_pdo();

// Filtres
$status_filter = isset($_GET['status']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['status']) : '';
$q             = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$sql    = "SELECT id, dossier_num, firstname, lastname, email, phone, country, city, urgency, status, has_missing_doc, created_at FROM gc_candidatures WHERE 1=1";
$params = [];
if ($status_filter !== '') {
    $sql     .= " AND status = ?";
    $params[] = $status_filter;
}
if ($q !== '') {
    $sql     .= " AND (dossier_num LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $like     = '%' . $q . '%';
    $params   = array_merge($params, [$like, $like, $like, $like, $like]);
}
$sql .= " ORDER BY id DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$status_labels = [
    'recue'           => ['label' => 'Reçue', 'color' => '#64748b'],
    'en_attente'      => ['label' => 'En attente', 'color' => '#64748b'],
    'en_verification' => ['label' => 'Vérification', 'color' => '#0ea5e9'],
    'en_analyse'      => ['label' => 'En analyse', 'color' => '#8b5cf6'],
    'liste_courte'    => ['label' => 'Liste courte', 'color' => '#f59e0b'],
    'retenue'         => ['label' => 'Retenue', 'color' => '#10b981'],
    'contactee'       => ['label' => 'Contactée', 'color' => '#06b6d4'],
    'injoignable'     => ['label' => 'Injoignable', 'color' => '#f97316'],
    'confirmee'       => ['label' => 'Confirmée', 'color' => '#22c55e'],
    'payee'           => ['label' => 'Payée', 'color' => '#16a34a'],
    'refusee'         => ['label' => 'Refusée', 'color' => '#ef4444'],
];
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatures — ACFE Admin</title>
    <style>
        :root { --gc-navy: #0f172a; --gc-orange: #ea580c; --gc-bg: #f8fafc; --gc-border: #e2e8f0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: var(--gc-bg); color: #0f172a; }
        .topbar { background: var(--gc-navy); color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        .topbar a { color: #fdba74; text-decoration: none; font-weight: 600; margin-left: 16px; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        h1 { font-size: 22px; font-weight: 800; color: var(--gc-navy); margin: 0 0 16px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .filter-input, select { padding: 8px 12px; border: 1px solid var(--gc-border); border-radius: 6px; font-size: 14px; }
        .btn { padding: 9px 16px; background: var(--gc-navy); color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-export { padding: 9px 16px; background: var(--gc-orange); color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 13.5px; font-weight: 700; display: inline-block; border: none; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--gc-border); font-size: 13.5px; }
        th { background: #f1f5f9; font-weight: 700; color: #334155; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #fff; }
        .action-link { color: var(--gc-orange); font-weight: 700; text-decoration: none; }
        .action-link:hover { text-decoration: underline; }
        .muted { color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
<div class="topbar">
    <div><strong>ACFE Admin</strong> — Candidatures</div>
    <div>
        <span class="muted" style="color:#94a3b8;">Connecté : <?php echo e($_SESSION['admin_username'] ?? ''); ?></span>
        <a href="settings.php">Sécurité</a>
        <a href="logout.php">Déconnexion</a>
    </div>
</div>
<div class="container">
    <h1>Dossiers de candidature</h1>

    <div class="toolbar">
        <form method="get" class="filters" action="index.php">
            <input type="text" name="q" class="filter-input" placeholder="Rechercher (n°, nom, email…)" value="<?php echo e($q); ?>" style="min-width:220px;">
            <select name="status">
                <option value="">Tous les statuts</option>
                <?php foreach ($status_labels as $key => $meta): ?>
                    <option value="<?php echo e($key); ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>><?php echo e($meta['label']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn">Filtrer</button>
        </form>

        <div>
            <form method="post" action="export.php" style="display:inline-flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <?php echo csrf_field(); ?>
                <input type="password" name="current_password" class="filter-input" placeholder="Mot de passe pour confirmer l'export" required autocomplete="current-password" style="width:260px;">
                <button type="submit" class="btn-export">Exporter en CSV &rarr;</button>
            </form>
        </div>
    </div>

    <p class="muted"><?php echo count($rows); ?> dossier(s) affiché(s) (max. 200)</p>

    <table>
        <thead>
            <tr>
                <th>Dossier</th>
                <th>Candidat</th>
                <th>Contact</th>
                <th>Lieu</th>
                <th>Urgence</th>
                <th>Statut</th>
                <th>Dépôt</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="muted">Aucun dossier trouvé.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $c):
                    $st = $status_labels[$c['status']] ?? ['label' => $c['status'], 'color' => '#64748b'];
                ?>
                    <tr>
                        <td><strong><?php echo e($c['dossier_num']); ?></strong></td>
                        <td><?php echo e($c['firstname'] . ' ' . $c['lastname']); ?></td>
                        <td>
                            <div><?php echo e($c['email']); ?></div>
                            <div class="muted"><?php echo e($c['phone']); ?></div>
                        </td>
                        <td><?php echo e(trim(($c['city'] ?? '') . ', ' . ($c['country'] ?? ''), ', ')); ?></td>
                        <td><?php echo e($c['urgency'] ?? ''); ?></td>
                        <td><span class="badge" style="background:<?php echo e($st['color']); ?>;"><?php echo e($st['label']); ?></span></td>
                        <td class="muted"><?php echo e(date('d/m/Y H:i', strtotime($c['created_at']))); ?></td>
                        <td><a href="candidature.php?id=<?php echo (int)$c['id']; ?>" class="action-link">Examiner &rarr;</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
