<?php
/**
 * Fiche Détail Candidat & Décision — ACFE Admin
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/csrf.php';

check_admin_auth();

$pdo = get_admin_pdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

$notice_msg = '';
$notice_type = '';

// Traitement du changement de statut ou de notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_status  = trim((string)($_POST['new_status'] ?? ''));
    $reason      = trim((string)($_POST['reason'] ?? ''));
    $admin_notes = trim((string)($_POST['admin_notes'] ?? ''));

    $valid_statuses = ['recue', 'en_analyse', 'retenue', 'refusee', 'en_attente'];

    if (!in_array($new_status, $valid_statuses, true)) {
        $notice_msg = "Statut invalide.";
        $notice_type = "error";
    } elseif ($new_status === 'refusee' && empty($reason)) {
        $notice_msg = "Le motif est obligatoire pour notifier un refus.";
        $notice_type = "error";
    } else {
        // Récupérer le statut actuel
        $cur_stmt = $pdo->prepare("SELECT status FROM gc_candidatures WHERE id = ?");
        $cur_stmt->execute([$id]);
        $old_status = $cur_stmt->fetchColumn();

        // Mettre à jour la candidature
        $upd = $pdo->prepare("UPDATE gc_candidatures SET status = ?, admin_notes = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$new_status, $admin_notes, $id]);

        // Si le statut a changé, consigner dans l'historique
        if ($old_status !== $new_status) {
            $hist = $pdo->prepare("INSERT INTO gc_candidature_history (candidature_id, admin_username, old_status, new_status, reason, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $hist->execute([$id, $_SESSION['admin_username'], $old_status, $new_status, $reason]);
        }

        $notice_msg = "Dossier mis à jour avec succès.";
        $notice_type = "success";
    }
}

// Récupérer les données fraîches de la candidature
$stmt = $pdo->prepare("SELECT * FROM gc_candidatures WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$cand = $stmt->fetch();

if (!$cand) {
    die("Dossier introuvable.");
}

// Historique
$hist_stmt = $pdo->prepare("SELECT * FROM gc_candidature_history WHERE candidature_id = ? ORDER BY id DESC");
$hist_stmt->execute([$id]);
$history = $hist_stmt->fetchAll();

// Libellés statut
$status_labels = [
    'recue'      => ['label' => 'Reçue', 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'en_analyse' => ['label' => 'En cours d’analyse', 'color' => '#d97706', 'bg' => '#fffbeb'],
    'retenue'    => ['label' => 'Retenue (Sélectionné)', 'color' => '#059669', 'bg' => '#ecfdf5'],
    'refusee'    => ['label' => 'Non retenue', 'color' => '#dc2626', 'bg' => '#fef2f2'],
    'en_attente' => ['label' => 'En attente de pièces', 'color' => '#6b7280', 'bg' => '#f3f4f6'],
];

$cur_stat = $status_labels[$cand['status']] ?? ['label' => $cand['status'], 'color' => '#334155', 'bg' => '#f1f5f9'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>Dossier <?php echo e($cand['dossier_num']); ?> — ACFE Admin</title>
    <style>
        :root {
            --gc-navy: #0b1a2d;
            --gc-orange: #c05621;
            --gc-slate: #1e293b;
            --gc-border: #e2e8f0;
            --gc-bg: #f8fafc;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--gc-bg);
            color: var(--gc-slate);
            line-height: 1.6;
        }
        .admin-nav {
            background: var(--gc-navy);
            color: #ffffff;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .nav-brand { font-size: 16px; font-weight: 800; color: #ffffff; text-decoration: none; }
        .nav-links a { color: #cbd5e1; text-decoration: none; font-size: 14px; margin-left: 18px; font-weight: 600; }
        .nav-links a:hover { color: #ffffff; }
        .container { max-width: 1080px; margin: 30px auto; padding: 0 20px; }
        .back-link { display: inline-block; margin-bottom: 20px; font-weight: 600; font-size: 14px; color: var(--gc-orange); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .card { background: #ffffff; border-radius: 12px; border: 1px solid var(--gc-border); padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--gc-border); padding-bottom: 18px; margin-bottom: 24px; }
        .dossier-title { font-size: 22px; font-weight: 800; color: var(--gc-navy); }
        .badge { padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 13px; display: inline-block; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .data-group { margin-bottom: 16px; }
        .data-label { font-size: 12.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .data-value { font-size: 15px; color: #0f172a; font-weight: 600; }
        .narrative-box { background: #f8fafc; border: 1px solid var(--gc-border); border-radius: 8px; padding: 20px; font-size: 14.5px; line-height: 1.8; color: #334155; white-space: pre-wrap; margin-top: 10px; }
        .btn-doc { display: inline-block; padding: 8px 16px; background: #0f172a; color: #ffffff !important; text-decoration: none !important; border-radius: 6px; font-size: 13.5px; font-weight: 600; transition: background 0.2s; }
        .btn-doc:hover { background: var(--gc-orange); }
        .missing-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 14px 18px; color: #92400e; margin-top: 12px; }
        .form-control { width: 100%; padding: 10px 12px; border: 1.5px solid var(--gc-border); border-radius: 6px; font-size: 14.5px; margin-top: 6px; }
        .btn-save { padding: 12px 24px; background: var(--gc-orange); color: #ffffff; border: none; border-radius: 6px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 16px; }
        .btn-save:hover { background: #9c4215; }
        .alert-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--gc-border); font-size: 13.5px; }
        th { background: #f8fafc; color: #64748b; font-weight: 700; }
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

    <!-- Fiche Candidat -->
    <div class="card">
        <div class="card-header">
            <div>
                <h1 class="dossier-title">Dossier : <?php echo e($cand['dossier_num']); ?></h1>
                <p style="color:#64748b; font-size:13.5px; margin-top:4px;">Reçu le <?php echo e(date('d/m/Y à H:i', strtotime($cand['created_at']))); ?></p>
            </div>
            <div>
                <span class="badge" style="background:<?php echo e($cur_stat['bg']); ?>; color:<?php echo e($cur_stat['color']); ?>; border:1px solid <?php echo e($cur_stat['color']); ?>;">
                    <?php echo e($cur_stat['label']); ?>
                </span>
            </div>
        </div>

        <div class="grid-2">
            <div>
                <div class="data-group">
                    <div class="data-label">Identité</div>
                    <div class="data-value"><?php echo e($cand['firstname'] . ' ' . $cand['lastname']); ?></div>
                </div>
                <div class="data-group">
                    <div class="data-label">Adresse E-mail (Vérifiée)</div>
                    <div class="data-value"><a href="mailto:<?php echo e($cand['email']); ?>" style="color:var(--gc-navy);"><?php echo e($cand['email']); ?></a></div>
                </div>
                <div class="data-group">
                    <div class="data-label">Téléphone joignable</div>
                    <div class="data-value"><a href="tel:<?php echo e($cand['phone']); ?>" style="color:var(--gc-navy);"><?php echo e($cand['phone']); ?></a></div>
                </div>
                <div class="data-group">
                    <div class="data-label">Localisation</div>
                    <div class="data-value"><?php echo e($cand['city'] . ', ' . $cand['country']); ?></div>
                </div>
            </div>

            <div>
                <div class="data-group">
                    <div class="data-label">Degré d'urgence déclaré</div>
                    <div class="data-value">
                        <?php if ($cand['urgency'] === 'urgente'): ?>
                            <span class="badge" style="background:#fee2e2; color:#991b1b; border:1px solid #f87171;">CRITIQUE (&lt; 7 JOURS)</span>
                        <?php elseif ($cand['urgency'] === 'moderee'): ?>
                            <span class="badge" style="background:#ffedd5; color:#9a3412; border:1px solid #fb923c;">Court terme</span>
                        <?php else: ?>
                            <span class="badge" style="background:#f1f5f9; color:#475569;">Planifié</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="data-group">
                    <div class="data-label">Situation professionnelle</div>
                    <div class="data-value"><?php echo e($cand['situation_pro']); ?></div>
                </div>
                <div class="data-group">
                    <div class="data-label">Composition du foyer</div>
                    <div class="data-value"><?php echo e($cand['foyer']); ?></div>
                </div>
                <div class="data-group">
                    <div class="data-label">Adresse IP de soumission</div>
                    <div class="data-value" style="font-family:monospace; font-size:13px;"><?php echo e($cand['ip_address'] ?? 'Non enregistrée'); ?></div>
                </div>
            </div>
        </div>

        <div style="margin-top:20px;">
            <div class="data-label">Récit et description détaillée de la situation</div>
            <div class="narrative-box"><?php echo e($cand['situation_desc']); ?></div>
        </div>

        <!-- Pièces Justificatives Sécurisées -->
        <div style="margin-top:28px; padding-top:20px; border-top:1px solid var(--gc-border);">
            <div class="data-label" style="margin-bottom:12px;">Pièces Justificatives (Stockage privé hors web)</div>
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                <?php
                $token_id = hash_hmac('sha256', "dl_{$cand['id']}_id", GC_CSRF_SECRET);
                $token_sit = hash_hmac('sha256', "dl_{$cand['id']}_situation", GC_CSRF_SECRET);
                ?>

                <div>
                    <?php if (!empty($cand['id_document'])): ?>
                        <a href="download.php?id=<?php echo $cand['id']; ?>&type=id&token=<?php echo $token_id; ?>" target="_blank" class="btn-doc">
                            Consulter la Pièce d'identité &rarr;
                        </a>
                        <div style="font-size:12px; color:#64748b; margin-top:4px;">Original : <?php echo e($cand['id_document_orig'] ?: $cand['id_document']); ?></div>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:13.5px;">Aucune pièce d'identité fournie</span>
                    <?php endif; ?>
                </div>

                <div>
                    <?php if (!empty($cand['situation_document'])): ?>
                        <a href="download.php?id=<?php echo $cand['id']; ?>&type=situation&token=<?php echo $token_sit; ?>" target="_blank" class="btn-doc">
                            Consulter le Justificatif de situation &rarr;
                        </a>
                        <div style="font-size:12px; color:#64748b; margin-top:4px;">Original : <?php echo e($cand['situation_document_orig'] ?: $cand['situation_document']); ?></div>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:13.5px;">Aucun justificatif de situation fourni</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($cand['has_missing_doc']) || !empty($cand['missing_doc_reason'])): ?>
                <div class="missing-box">
                    <strong>Déclaration de document manquant par le candidat :</strong><br>
                    <?php echo e($cand['missing_doc_reason'] ?: 'Aucun motif renseigné.'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Formulaire Décision & Statut -->
    <div class="card">
        <h2 style="font-size:18px; font-weight:800; color:var(--gc-navy); margin-bottom:16px;">Décision &amp; Statut du dossier</h2>
        <form method="post" action="candidature.php?id=<?php echo $cand['id']; ?>">
            <?php echo csrf_field(); ?>

            <div class="grid-2">
                <div>
                    <label class="data-label" for="new_status">Statut de la candidature</label>
                    <select name="new_status" id="new_status" class="form-control" required>
                        <?php foreach ($status_labels as $s_key => $s_data): ?>
                            <option value="<?php echo e($s_key); ?>" <?php echo $cand['status'] === $s_key ? 'selected' : ''; ?>>
                                <?php echo e($s_data['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="data-label" for="reason">Motif (Obligatoire si refus)</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="Raison de la décision...">
                </div>
            </div>

            <div style="margin-top:16px;">
                <label class="data-label" for="admin_notes">Notes internes de l'équipe (Confidentiel)</label>
                <textarea name="admin_notes" id="admin_notes" rows="3" class="form-control" placeholder="Remarques de l'évaluateur, vérifications effectuées..."><?php echo e($cand['admin_notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn-save">Enregistrer la décision &rarr;</button>
        </form>
    </div>

    <!-- Historique des Décisions -->
    <div class="card">
        <h2 style="font-size:18px; font-weight:800; color:var(--gc-navy); margin-bottom:8px;">Historique des décisions</h2>
        <?php if (empty($history)): ?>
            <p style="color:#64748b; font-size:14px;">Aucun historique de changement pour ce dossier.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date &amp; Heure</th>
                        <th>Administrateur</th>
                        <th>Transition</th>
                        <th>Motif</th>
                    </tr>
                </thead>
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
