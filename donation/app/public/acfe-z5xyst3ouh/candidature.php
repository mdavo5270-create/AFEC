<?php
/**
 * Fiche Détail Candidat & Décision — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/audit.php';

check_admin_auth();

$pdo = get_admin_pdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$notice_msg = '';
$notice_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_status  = trim((string)($_POST['new_status'] ?? ''));
    $reason      = trim((string)($_POST['reason'] ?? ''));
    $admin_notes = trim((string)($_POST['admin_notes'] ?? ''));

    $valid_statuses = ['recue', 'en_analyse', 'retenue', 'refusee', 'en_attente', 'en_verification', 'liste_courte', 'contactee', 'injoignable', 'confirmee', 'payee'];

    if (!in_array($new_status, $valid_statuses, true)) {
        $notice_msg = "Statut invalide.";
        $notice_type = "error";
    } elseif ($new_status === 'refusee' && empty($reason)) {
        $notice_msg = "Le motif est obligatoire pour notifier un refus.";
        $notice_type = "error";
    } else {
        $pdo->beginTransaction();
        try {
            $cur_stmt = $pdo->prepare("SELECT status FROM gc_candidatures WHERE id = ? FOR UPDATE");
            $cur_stmt->execute([$id]);
            $old_status = $cur_stmt->fetchColumn();

            if ($old_status === false) {
                $pdo->rollBack();
                $notice_msg = "Dossier introuvable.";
                $notice_type = "error";
            } else {
                $upd = $pdo->prepare("UPDATE gc_candidatures SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$new_status, mb_substr($admin_notes, 0, 5000), $id]);

                if ($old_status !== $new_status) {
                    $hist = $pdo->prepare("INSERT INTO gc_candidature_history (candidature_id, admin_username, old_status, new_status, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $hist->execute([$id, $_SESSION['admin_username'], $old_status, $new_status, mb_substr($reason, 0, 1000)]);
                }

                $pdo->commit();
                $notice_msg = "Dossier mis à jour avec succès.";
                $notice_type = "success";
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Erreur de mise à jour du dossier #' . $id . ' : ' . $e->getMessage());
            $notice_msg = "La mise à jour a échoué. Aucune modification n'a été enregistrée.";
            $notice_type = "error";
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM gc_candidatures WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cand = $stmt->fetch();

if (!$cand) {
    die("Dossier introuvable.");
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    gc_audit('view_candidature', 'candidature:' . (int)$cand['id']);
}

$hist_stmt = $pdo->prepare("SELECT * FROM gc_candidature_history WHERE candidature_id = ? ORDER BY id DESC");
$hist_stmt->execute([$id]);
$history = $hist_stmt->fetchAll();

$status_labels = [
    'recue'           => ['label' => 'Reçue', 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'en_analyse'      => ['label' => 'En cours d\'analyse', 'color' => '#d97706', 'bg' => '#fffbeb'],
    'retenue'         => ['label' => 'Retenue', 'color' => '#059669', 'bg' => '#ecfdf5'],
    'refusee'         => ['label' => 'Non retenue', 'color' => '#dc2626', 'bg' => '#fef2f2'],
    'en_attente'      => ['label' => 'En attente de pièces', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
    'en_verification' => ['label' => 'Vérification', 'color' => '#0ea5e9', 'bg' => '#f0f9ff'],
    'liste_courte'    => ['label' => 'Liste courte', 'color' => '#f59e0b', 'bg' => '#fffbeb'],
    'contactee'       => ['label' => 'Contactée', 'color' => '#06b6d4', 'bg' => '#ecfeff'],
    'injoignable'     => ['label' => 'Injoignable', 'color' => '#f97316', 'bg' => '#fff7ed'],
    'confirmee'       => ['label' => 'Confirmée', 'color' => '#22c55e', 'bg' => '#f0fdf4'],
    'payee'           => ['label' => 'Payée', 'color' => '#16a34a', 'bg' => '#f0fdf4'],
];

$cur_stat = $status_labels[$cand['status']] ?? ['label' => $cand['status'], 'color' => '#334155', 'bg' => '#f1f5f9'];
$link_id  = gc_download_url((int)$cand['id'], 'id');
$link_sit = gc_download_url((int)$cand['id'], 'situation');
?><!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Dossier <?php echo e($cand['dossier_num']); ?> — ACFE Admin</title>
    <style>
        :root { --gc-navy:#0b1a2d; --gc-orange:#c05621; --gc-slate:#1e293b; --gc-border:#e2e8f0; --gc-bg:#f8fafc; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; background:var(--gc-bg); color:var(--gc-slate); line-height:1.6; }
        .admin-nav { background:var(--gc-navy); color:#fff; padding:14px 28px; display:flex; justify-content:space-between; align-items:center; }
        .nav-brand { font-size:16px; font-weight:800; color:#fff; text-decoration:none; }
        .nav-links a { color:#cbd5e1; text-decoration:none; font-size:14px; margin-left:18px; font-weight:600; }
        .container { max-width:1080px; margin:30px auto; padding:0 20px; }
        .back-link { display:inline-block; margin-bottom:20px; font-weight:600; font-size:14px; color:var(--gc-orange); text-decoration:none; }
        .card { background:#fff; border-radius:12px; border:1px solid var(--gc-border); padding:30px; margin-bottom:24px; }
        .card-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--gc-border); padding-bottom:18px; margin-bottom:24px; }
        .dossier-title { font-size:22px; font-weight:800; color:var(--gc-navy); }
        .badge { padding:6px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .data-group { margin-bottom:16px; }
        .data-label { font-size:12.5px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
        .data-value { font-size:15px; color:#0f172a; font-weight:600; }
        .narrative-box { background:#f8fafc; border:1px solid var(--gc-border); border-radius:8px; padding:20px; font-size:14.5px; white-space:pre-wrap; margin-top:10px; }
        .btn-doc { display:inline-block; padding:8px 16px; background:#0f172a; color:#fff !important; text-decoration:none !important; border-radius:6px; font-size:13.5px; font-weight:600; }
        .form-control { width:100%; padding:10px 12px; border:1.5px solid var(--gc-border); border-radius:6px; font-size:14.5px; margin-top:6px; }
        .btn-save { padding:12px 24px; background:var(--gc-orange); color:#fff; border:none; border-radius:6px; font-weight:700; font-size:15px; cursor:pointer; margin-top:16px; }
        .alert-success { background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px 16px; border-radius:8px; margin-bottom:20px; font-weight:600; }
        table { width:100%; border-collapse:collapse; margin-top:14px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--gc-border); font-size:13.5px; }
        th { background:#f8fafc; color:#64748b; font-weight:700; }
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
    <a href="index.php" class="back-link">&larr; Retour à la liste des candidatures</a>
    <?php if ($notice_msg !== ''): ?>
        <div class="alert-<?php echo e($notice_type); ?>"><?php echo e($notice_msg); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h1 class="dossier-title">Dossier : <?php echo e($cand['dossier_num']); ?></h1>
                <p style="color:#64748b; font-size:13.5px; margin-top:4px;">Reçu le <?php echo e(date('d/m/Y à H:i', strtotime($cand['created_at']))); ?></p>
            </div>
            <span class="badge" style="background:<?php echo e($cur_stat['bg']); ?>; color:<?php echo e($cur_stat['color']); ?>; border:1px solid <?php echo e($cur_stat['color']); ?>;"><?php echo e($cur_stat['label']); ?></span>
        </div>
        <div class="grid-2">
            <div>
                <div class="data-group"><div class="data-label">Identité</div><div class="data-value"><?php echo e($cand['firstname'] . ' ' . $cand['lastname']); ?></div></div>
                <div class="data-group"><div class="data-label">E-mail</div><div class="data-value"><?php echo e($cand['email']); ?></div></div>
                <div class="data-group"><div class="data-label">Téléphone</div><div class="data-value"><?php echo e($cand['phone']); ?></div></div>
                <div class="data-group"><div class="data-label">Localisation</div><div class="data-value"><?php echo e(($cand['city'] ?? '') . ', ' . ($cand['country'] ?? '')); ?></div></div>
            </div>
            <div>
                <div class="data-group"><div class="data-label">Urgence</div><div class="data-value"><?php echo e($cand['urgency'] ?? ''); ?></div></div>
                <div class="data-group"><div class="data-label">Situation pro</div><div class="data-value"><?php echo e($cand['situation_pro'] ?? ''); ?></div></div>
                <div class="data-group"><div class="data-label">Foyer</div><div class="data-value"><?php echo e($cand['foyer'] ?? ''); ?></div></div>
                <div class="data-group"><div class="data-label">IP soumission</div><div class="data-value" style="font-family:monospace;font-size:13px;"><?php echo e($cand['ip_address'] ?? '—'); ?></div></div>
            </div>
        </div>
        <div style="margin-top:20px;">
            <div class="data-label">Description de la situation</div>
            <div class="narrative-box"><?php echo e($cand['situation_desc'] ?? ''); ?></div>
        </div>
        <div style="margin-top:28px; padding-top:20px; border-top:1px solid var(--gc-border);">
            <div class="data-label" style="margin-bottom:12px;">Pièces justificatives (stockage privé)</div>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <div>
                    <?php if (!empty($cand['id_document'])): ?>
                        <a href="<?php echo e($link_id); ?>" target="_blank" rel="noopener noreferrer" class="btn-doc">Consulter la pièce d'identité &rarr;</a>
                        <div style="font-size:12px;color:#64748b;margin-top:4px;">Original : <?php echo e($cand['id_document_orig'] ?: $cand['id_document']); ?></div>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:13.5px;">Aucune pièce d'identité</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if (!empty($cand['situation_document'])): ?>
                        <a href="<?php echo e($link_sit); ?>" target="_blank" rel="noopener noreferrer" class="btn-doc">Consulter le justificatif de situation &rarr;</a>
                        <div style="font-size:12px;color:#64748b;margin-top:4px;">Original : <?php echo e($cand['situation_document_orig'] ?: $cand['situation_document']); ?></div>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:13.5px;">Aucun justificatif de situation</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="font-size:18px;font-weight:800;color:var(--gc-navy);margin-bottom:16px;">Décision &amp; Statut</h2>
        <form method="post" action="candidature.php?id=<?php echo (int)$cand['id']; ?>">
            <?php echo csrf_field(); ?>
            <div class="grid-2">
                <div>
                    <label class="data-label" for="new_status">Statut</label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <?php foreach ($status_labels as $s_key => $s_data): ?>
                            <option value="<?php echo e($s_key); ?>" <?php echo $cand['status'] === $s_key ? 'selected' : ''; ?>><?php echo e($s_data['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="data-label" for="reason">Motif (obligatoire si refus)</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="Raison de la décision...">
                </div>
            </div>
            <div style="margin-top:16px;">
                <label class="data-label" for="admin_notes">Notes internes</label>
                <textarea name="admin_notes" id="admin_notes" rows="3" class="form-control"><?php echo e($cand['admin_notes'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn-save">Enregistrer la décision &rarr;</button>
        </form>
    </div>

    <div class="card">
        <h2 style="font-size:18px;font-weight:800;color:var(--gc-navy);margin-bottom:8px;">Historique</h2>
        <?php if (empty($history)): ?>
            <p style="color:#64748b;font-size:14px;">Aucun historique.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Admin</th><th>Transition</th><th>Motif</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?php echo e(date('d/m/Y H:i', strtotime($h['created_at']))); ?></td>
                        <td><strong><?php echo e($h['admin_username']); ?></strong></td>
                        <td><?php echo e($h['old_status'] ?: '—'); ?> &rarr; <strong><?php echo e($h['new_status']); ?></strong></td>
                        <td><?php echo e($h['reason'] ?: '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
