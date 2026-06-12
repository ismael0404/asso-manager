<?php
/**
 * Dashboard Administrateur v3.0
 * Fichier: admin/dashboard.php
 * Ajouts: participations en attente, stats utilisateurs, messages privés
 */
$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Statistiques
$stats = [];
$stats['activities'] = $pdo->query("SELECT COUNT(*) as c FROM activities")->fetch()['c'];
$stats['members'] = $pdo->query("SELECT COUNT(*) as c FROM members")->fetch()['c'];
$stats['posts'] = $pdo->query("SELECT COUNT(*) as c FROM posts")->fetch()['c'];
$stats['users'] = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$stats['contacts'] = $pdo->query("SELECT COUNT(*) as c FROM contacts WHERE is_read=0")->fetch()['c'];
$stats['participations'] = $pdo->query("SELECT COUNT(*) as c FROM participations WHERE status='accepted'")->fetch()['c'];
$stats['pending'] = $pdo->query("SELECT COUNT(*) as c FROM participations WHERE status='pending'")->fetch()['c'];
$stats['messages'] = $pdo->query("SELECT COUNT(*) as c FROM messages")->fetch()['c'];

// Activités par mois (12 derniers mois)
$activitiesByMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total 
    FROM activities 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

$monthLabels = [];
$monthData = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $monthLabels[] = date('M Y', strtotime("-$i months"));
    $found = false;
    foreach ($activitiesByMonth as $row) {
        if ($row['month'] === $m) {
            $monthData[] = (int)$row['total'];
            $found = true;
            break;
        }
    }
    if (!$found) $monthData[] = 0;
}

// Nouveaux membres par mois
$membersByMonth = $pdo->query("
    SELECT DATE_FORMAT(membership_date, '%Y-%m') as month, COUNT(*) as total 
    FROM members 
    WHERE membership_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

$memberData = [];
for ($i = 11; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $found = false;
    foreach ($membersByMonth as $row) {
        if ($row['month'] === $m) {
            $memberData[] = (int)$row['total'];
            $found = true;
            break;
        }
    }
    if (!$found) $memberData[] = 0;
}

// Dernières activités
$recentAct = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM participations WHERE activity_id=a.id AND status='accepted') as participants, (SELECT COUNT(*) FROM participations WHERE activity_id=a.id AND status='pending') as pending FROM activities a ORDER BY a.created_at DESC LIMIT 5")->fetchAll();
// Derniers messages
$recentMsg = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5")->fetchAll();
// Derniers logs
$recentLogs = $pdo->query("SELECT l.*, u.full_name FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 5")->fetchAll();

// Participations en attente (alerte)
$pendingParticipationsList = $pdo->query("
    SELECT p.*, u.full_name, a.title as activity_title
    FROM participations p 
    JOIN users u ON p.user_id = u.id 
    JOIN activities a ON p.activity_id = a.id 
    WHERE p.status = 'pending' 
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Bienvenue, <?php echo e($_SESSION['user_name'] ?? 'Admin'); ?> ! Voici un aperçu de votre association.</p>
            </div>
        </div>
        
        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['activities']; ?></h3>
                    <p>Activités</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['members']; ?></h3>
                    <p>Membres</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-newspaper"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['posts']; ?></h3>
                    <p>Publications</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-hand-paper"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['participations']; ?></h3>
                    <p>Participations
                        <?php if ($stats['pending'] > 0): ?>
                            <span class="header-badge" style="font-size:0.65rem;"><?php echo $stats['pending']; ?> en attente</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Participations en attente (alerte) -->
        <div class="pending-alert-card mb-3">
            <div class="table-header">
                <h3><i class="fas fa-hourglass-half"></i> Demandes de participation en attente
                    <span class="header-badge"><?php echo $stats['pending']; ?></span>
                </h3>
                <a href="<?php echo BASE_URL; ?>/admin/activities.php" class="btn btn-sm btn-primary">Gérer</a>
            </div>
            <div class="pending-list">
                <?php 
                if (!empty($pendingParticipationsList) && (is_array($pendingParticipationsList) || is_object($pendingParticipationsList))):
                    foreach ($pendingParticipationsList as $pp): 
                ?>
                <div class="pending-item">
                    <div class="pending-icon"><i class="fas fa-user-clock"></i></div>
                    <div class="pending-content">
                        <p><strong><?php echo e($pp['full_name']); ?></strong> souhaite participer à <strong>"<?php echo e($pp['activity_title']); ?>"</strong></p>
                        <span class="pending-time"><?php echo timeAgo($pp['created_at']); ?></span>
                    </div>
                    <div class="pending-actions">
                        <form method="POST" action="<?php echo BASE_URL; ?>/admin/activities.php" style="display:inline;">
                            <input type="hidden" name="action" value="accept_participation">
                            <input type="hidden" name="participation_id" value="<?php echo $pp['id']; ?>">
                            <button type="submit" class="btn btn-icon btn-success" title="Accepter"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" action="<?php echo BASE_URL; ?>/admin/activities.php" style="display:inline;">
                            <input type="hidden" name="action" value="reject_participation">
                            <input type="hidden" name="participation_id" value="<?php echo $pp['id']; ?>">
                            <button type="submit" class="btn btn-icon btn-danger" title="Refuser"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                </div>
                <?php 
                    endforeach; 
                else: 
                ?>
                <div class="pending-item">
                    <div class="pending-content">
                        <p style="color:var(--text-muted); text-align:center; padding: 10px 0;">
                            <i class="fas fa-check-circle"></i> Aucune demande de participation en attente pour le moment.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Charts -->
        <div class="grid-2 mb-3">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-bar"></i> Activités par mois</h3>
                </div>
                <div class="chart-body">
                    <canvas id="activitiesChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Nouveaux membres par mois</h3>
                </div>
                <div class="chart-body">
                    <canvas id="membersChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Récent -->
        <div class="grid-2">
            <!-- Dernières activités -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-calendar"></i> Dernières Activités</h3>
                    <a href="<?php echo BASE_URL; ?>/admin/activities.php" class="btn btn-sm btn-secondary">Tout voir</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Participants</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAct as $act): ?>
                        <tr>
                            <td class="truncate"><?php echo e($act['title']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($act['activity_date'])); ?></td>
                            <td>
                                <span class="participant-count-inline"><i class="fas fa-users"></i> <?php echo $act['participants']; ?></span>
                                <?php if ($act['pending'] > 0): ?>
                                    <span class="pending-badge-sm"><?php echo $act['pending']; ?> ⏳</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?php echo e($act['status']); ?>"><?php echo e(translateStatus($act['status'])); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentAct)): ?>
                        <tr><td colspan="4" class="text-center" style="padding:20px;color:var(--text-muted);">Aucune activité</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Derniers messages -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-envelope"></i> Derniers Messages</h3>
                    <a href="<?php echo BASE_URL; ?>/admin/messages.php" class="btn btn-sm btn-secondary">Tout voir</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>De</th>
                            <th>Sujet</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMsg as $msg): ?>
                        <tr>
                            <td><?php echo e($msg['name']); ?></td>
                            <td class="truncate"><?php echo e($msg['subject']); ?></td>
                            <td>
                                <?php if ($msg['is_read']): ?>
                                    <span class="badge badge-completed">Lu</span>
                                <?php else: ?>
                                    <span class="badge badge-ongoing">Non lu</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentMsg)): ?>
                        <tr><td colspan="3" class="text-center" style="padding:20px;color:var(--text-muted);">Aucun message</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Derniers logs -->
        <div class="table-card mt-3">
            <div class="table-header">
                <h3><i class="fas fa-history"></i> Activité Récente</h3>
                <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="btn btn-sm btn-secondary">Tout voir</a>
            </div>
            <div class="log-list">
                <?php foreach ($recentLogs as $log): ?>
                <div class="log-item">
                    <div class="log-icon"><i class="fas fa-clock"></i></div>
                    <div class="log-content">
                        <p><strong><?php echo e($log['full_name'] ?? 'Système'); ?></strong> <?php echo e($log['action']); ?></p>
                        <span class="log-time"><?php echo timeAgo($log['created_at']); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recentLogs)): ?>
                <div class="log-item"><div class="log-content"><p style="color:var(--text-muted);">Aucune activité récente</p></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark-mode');
    const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';

    new Chart(document.getElementById('activitiesChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Activités',
                data: <?php echo json_encode($monthData); ?>,
                backgroundColor: 'rgba(37, 99, 235, 0.7)',
                borderColor: 'rgba(37, 99, 235, 1)',
                borderWidth: 2, borderRadius: 8, borderSkipped: false
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor, maxRotation: 45 }, grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('membersChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($monthLabels); ?>,
            datasets: [{
                label: 'Nouveaux membres',
                data: <?php echo json_encode($memberData); ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                borderWidth: 3, fill: true, tension: 0.4,
                pointBackgroundColor: '#10b981', pointBorderColor: '#fff',
                pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1, color: textColor }, grid: { color: gridColor } },
                x: { ticks: { color: textColor, maxRotation: 45 }, grid: { display: false } }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
