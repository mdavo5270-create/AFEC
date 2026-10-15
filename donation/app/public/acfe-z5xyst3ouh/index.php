<?php
/**
 * Tableau de Bord Principal — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';

check_admin_auth();

$pdo = get_admin_pdo();

// 1. Statistiques des dossiers
$total_cands   = (int)$pdo->query("SELECT COUNT(*) FROM gc_candidatures")->fetchColumn();
$recues_count  = (int)$pdo->query("SELECT COUNT(*) FROM gc_candidatures WHERE status IN ('recue', 'en_analyse')")->fetchColumn();
$retenues_count = (int)$pdo->query("SELECT COUNT(*) FROM gc_candidatures WHERE status = 'retenue'")->fetchColumn();
$refusees_count = (int)$pdo->query("SELECT COUNT(*) FROM gc_candidatures WHERE status = 'refusee'")->fetchColumn();
$places_max    = 58;
$places_rest   = max(0, $places_max - $retenues_count);

// 2. Filtres & Recherche
$filter_status  = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$filter_urgency = isset($_GET['urgency']) ? trim((string)$_GET['urgency']) : '';
$search_query   = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

$where_clauses = [];
$params        = [];

if ($filter_status !== '' && in_array($filter_status, ['recue', 'en_analyse', 'retenue', 'refusee', 'en_attente'], true)) {
    $where_clauses[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_urgency !== '' && in_array($filter_urgency, ['urgente', 'moderee', 'planifiee'], true)) {
    $where_clauses[] = "urgency = ?";
    $params[] = $filter_urgency;
}

if ($search_query !== '') {
    $where_clauses[] = "(dossier_num LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ? OR city LIKE ?)";
    $like = '%' . $search_query . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// 3. Pagination
$page     = max(1, (int)($_GET['p'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM gc_candidatures $where_sql");
$count_stmt->execute($params);
$filtered_total = (int)$count_stmt->fetchColumn();
$total_pages    = max(1, ceil($filtered_total / $per_page));

// 4. Récupération des dossiers
$list_stmt = $pdo->prepare("SELECT id, dossier_num, firstname, lastname, email, phone, country, city, urgency, status, id_document, situation_document, has_missing_doc, created_at FROM gc_candidatures $where_sql ORDER BY id DESC LIMIT $per_page OFFSET $offset");
$list_stmt->execute($params);
$candidatures = $list_stmt->fetchAll();

$status_labels = [
    'recue'      => ['label' => 'Reçue', 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'en_analyse' => ['label' => 'En analyse', 'color' => '#d97706', 'bg' => '#fffbeb'],
    'retenue'    => ['label' => 'Retenue', 'color' => '#059669', 'bg' => '#ecfdf5'],
    'refusee'    => ['label' => 'Refusée', 'color' => '#dc2626', 'bg' => '#fef2f2'],
    'en_attente' => ['label' => 'En attente', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Gestion des Candidatures — ACFE Admin</title>
    <style>
        :root {
            --gc-navy: #0b1a2d;
            --gc-orange: #c05621;
            --gc-slate: #1e293b;
            --gc-border: #e2e8f0;
            --gc-bg: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: var(--gc-bg); color: var(--gc-slate); line-height: 1.5; }
        .admin-nav { background: var(--gc-navy); color: #ffffff; padding: 14px 28px; display: flex; justify-content: space-between; align-items: center; }
        .nav-brand { font-size: 16px; font-weight: 800; color: #ffffff; text-decoration: none; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; margin-left: 18px; font-weight: 600; }
        .nav-links a:hover { color: #ffffff; }
        .container { max-width: 1240px; margin: 28px auto; padding: 0 20px; }
        
        /* Stats cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: #ffffff; border: 1px solid var(--gc-border); border-radius: 10px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gc-navy); }
        .stat-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        
        /* Filtres */
        .filter-bar { background: #ffffff; border: 1px solid var(--gc-border); border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; display: flex; gap: 14px; flex-wrap: wrap; align-items: center; justify-content: space-between; }
        .filter-form { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
        .filter-input { padding: 9px 12px; border: 1.5px solid var(--gc-border); border-radius: 6px; font-size: 13.5px; }
        .btn-filter { padding: 9px 16px; background: var(--gc-navy); color: #ffffff; border: none; border-radius: 6px; font-weight: 700; font-size: 13.5px; cursor: pointer; }
        .btn-reset { padding: 9px 14px; background: #f1f5f9; color: #475569; text-decoration: none; border-radius: 6px; font-size: 13.5px; font-weight: 600; }
        .btn-export { padding: 9px 16px; background: var(--gc-orange); color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 13.5px; font-weight: 700; display: inline-block; }
        
        /* Table */
        .table-card { background: #ffffff; border: 1px solid var(--gc-border); border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--gc-border); font-size: 14px; }
        th { background: #f8fafc; font-weight: 700; color: #475569; font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:hover td { background: #fbfcfe; }
        .badge { padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; display: inline-block; }
        .tag-doc { padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: 700; margin-right: 4px; display: inline-block; }
        .action-link { color: var(--gc-orange); font-weight: 700; text-decoration: none; }
        .action-link:hover { text-decoration: underline; }
        
        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 6px; padding: 20px; }
        .page-link { padding: 6px 12px; border: 1px solid var(--gc-border); background: #ffffff; border-radius: 4px; text-decoration: none; color: #334155; font-size: 13px; font-weight: 600; }
        .page-link.active { background: var(--gc-navy); color: #ffffff; border-color: var(--gc-navy); }
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
    <!-- Cartes Statistiques -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number"><?php echo $total_cands; ?></div>
            <div class="stat-label">Total Dossiers Reçus</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#d97706;"><?php echo $recues_count; ?></div>
            <div class="stat-label">En Attente d'Analyse</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:#059669;"><?php echo $retenues_count; ?></div>
            <div class="stat-label">Sélectionnés / Retenus</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" style="color:var(--gc-orange);"><?php echo $places_rest; ?> / <?php echo $places_max; ?></div>
            <div class="stat-label">Places Restantes</div>
        </div>
    </div>

    <!-- Barre de Filtres & Export -->
    <div class="filter-bar">
        <form method="get" action="index.php" class="filter-form">
            <input type="text" name="q" class="filter-input" placeholder="Rechercher nom, n°, tél..." value="<?php echo e($search_query); ?>" style="width:200px;">
            
            <select name="status" class="filter-input">
                <option value="">Tous les statuts</option>
                <?php foreach ($status_labels as $s_k => $s_v): ?>
                    <option value="<?php echo e($s_k); ?>" <?php echo $filter_status === $s_k ? 'selected' : ''; ?>>
                        <?php echo e($s_v['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="urgency" class="filter-input">
                <option value="">Toutes les urgences</option>
                <option value="urgente" <?php echo $filter_urgency === 'urgente' ? 'selected' : ''; ?>>Critique (&lt; 7j)</option>
                <option value="moderee" <?php echo $filter_urgency === 'moderee' ? 'selected' : ''; ?>>Court terme</option>
                <option value="planifiee" <?php echo $filter_urgency === 'planifiee' ? 'selected' : ''; ?>>Planifié</option>
            </select>

            <button type="submit" class="btn-filter">Filtrer</button>
            <?php if ($search_query !== '' || $filter_status !== '' || $filter_urgency !== ''): ?>
                <a href="index.php" class="btn-reset">Réinitialiser</a>
            <?php endif; ?>
        </form>

        <div>
            <form method="post" action="export.php" style="display:inline;">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn-export">Exporter en CSV &rarr;</button>
            </form>
        </div>
    </div>

    <!-- Tableau des Candidatures -->
    <div class="table-card">
        <?php if (empty($candidatures)): ?>
            <div style="padding:40px; text-align:center; color:#64748b;">
                Aucune candidature trouvée avec les critères sélectionnés.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>N° Dossier</th>
                        <th>Candidat</th>
                        <th>Urgence</th>
                        <th>Résidence</th>
                        <th>Contact</th>
                        <th>Justificatifs</th>
                        <th>Statut</th>
                        <th>Reçu le</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidatures as $c): ?>
                        <?php
                        $st = $status_labels[$c['status']] ?? ['label' => $c['status'], 'color' => '#334155', 'bg' => '#f1f5f9'];
                        ?>
                        <tr>
                            <td><strong><?php echo e($c['dossier_num']); ?></strong></td>
                            <td>
                                <strong><?php echo e($c['firstname'] . ' ' . $c['lastname']); ?></strong>
                            </td>
                            <td>
                                <?php if ($c['urgency'] === 'urgente'): ?>
                                    <span class="badge" style="background:#fee2e2; color:#991b1b;">CRITIQUE</span>
                                <?php elseif ($c['urgency'] === 'moderee'): ?>
                                    <span class="badge" style="background:#ffedd5; color:#9a3412;">Court terme</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#f1f5f9; color:#475569;">Planifié</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($c['city'] . ($c['country'] ? ' (' . $c['country'] . ')' : '')); ?></td>
                            <td>
                                <div><a href="tel:<?php echo e($c['phone']); ?>" style="color:#0f172a; text-decoration:none; font-weight:600;"><?php echo e($c['phone']); ?></a></div>
                                <div style="font-size:12px; color:#64748b;"><?php echo e($c['email']); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($c['id_document'])): ?>
                                    <span class="tag-doc" style="background:#dcfce7; color:#166534;">ID</span>
                                <?php endif; ?>
                                <?php if (!empty($c['situation_document'])): ?>
                                    <span class="tag-doc" style="background:#e0e7ff; color:#3730a3;">Situation</span>
                                <?php endif; ?>
                                <?php if (!empty($c['has_missing_doc'])): ?>
                                    <span class="tag-doc" style="background:#fef3c7; color:#92400e;" title="Document manquant déclaré">Manquant</span>
                                <?php endif; ?>
                                <?php if (empty($c['id_document']) && empty($c['situation_document']) && empty($c['has_missing_doc'])): ?>
                                    <span style="color:#94a3b8; font-size:12px;">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background:<?php echo e($st['bg']); ?>; color:<?php echo e($st['color']); ?>;">
                                    <?php echo e($st['label']); ?>
                                </span>
                            </td>
                            <td style="font-size:13px; color:#64748b;">
                                <?php echo e(date('d/m/Y H:i', strtotime($c['created_at']))); ?>
                            </td>
                            <td>
                                <a href="candidature.php?id=<?php echo $c['id']; ?>" class="action-link">Examiner &rarr;</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="index.php?p=<?php echo $i; ?><?php echo $filter_status ? '&status=' . urlencode($filter_status) : ''; ?><?php echo $filter_urgency ? '&urgency=' . urlencode($filter_urgency) : ''; ?><?php echo $search_query ? '&q=' . urlencode($search_query) : ''; ?>" class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
