<?php
/**
 * Mes activités - Historique des participations
 * Fichier: user/my-activities.php
 * Affiche les participations avec statut (pending/accepted/rejected)
 */
$pageTitle = 'Mes Activités';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Filtre par statut de participation
$filter = $_GET['filter'] ?? '';
$where = "WHERE p.user_id = ?";
$params = [$userId];

if ($filter && in_array($filter, ['pending', 'accepted', 'rejected'])) {
    $where .= " AND p.status = ?";
    $params[] = $filter;
}

// Pagination
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations p $where");
$totalStmt->execute($params);
$total = $totalStmt->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT a.*, p.status as participation_status, p.created_at as request_date, p.updated_at as status_updated
    FROM participations p 
    JOIN activities a ON p.activity_id = a.id 
    $where 
    ORDER BY p.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$myActivities = $stmt->fetchAll();

// Comptes par statut
$countAll = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ?");
$countAll->execute([$userId]);
$countAll = $countAll->fetch()['c'];

$countPending = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'pending'");
$countPending->execute([$userId]);
$countPending = $countPending->fetch()['c'];

$countAccepted = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'accepted'");
$countAccepted->execute([$userId]);
$countAccepted = $countAccepted->fetch()['c'];

$countRejected = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'rejected'");
$countRejected->execute([$userId]);
$countRejected = $countRejected->fetch()['c'];

// Traitement annulation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_id'])) {
    $cancelId = (int)$_POST['cancel_id'];
    $pdo->prepare("DELETE FROM participations WHERE user_id = ? AND activity_id = ? AND status = 'pending'")
        ->execute([$userId, $cancelId]);
    setFlash('success', 'Demande de participation annulée.');
    header('Location: ' . BASE_URL . '/user/my-activities.php');
    exit();
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-calendar-check"></i> Mes <span>Activités</span></h1>
        <p>Suivez l'état de vos demandes de participation</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:900px;">
        
        <!-- Stats rapides -->
        <div class="my-act-stats mb-3">
            <div class="mas-item">
                <span class="mas-count"><?php echo $countAll; ?></span>
                <span class="mas-label">Total</span>
            </div>
            <div class="mas-item mas-pending">
                <span class="mas-count"><?php echo $countPending; ?></span>
                <span class="mas-label">⏳ En attente</span>
            </div>
            <div class="mas-item mas-accepted">
                <span class="mas-count"><?php echo $countAccepted; ?></span>
                <span class="mas-label">✅ Acceptées</span>
            </div>
            <div class="mas-item mas-rejected">
                <span class="mas-count"><?php echo $countRejected; ?></span>
                <span class="mas-label">❌ Refusées</span>
            </div>
        </div>

        <!-- Filtres -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
            <a href="?filter=" class="btn <?php echo !$filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Toutes (<?php echo $countAll; ?>)</a>
            <a href="?filter=pending" class="btn <?php echo $filter === 'pending' ? 'btn-warning' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-hourglass-half"></i> En attente (<?php echo $countPending; ?>)</a>
            <a href="?filter=accepted" class="btn <?php echo $filter === 'accepted' ? 'btn-success' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-check"></i> Acceptées (<?php echo $countAccepted; ?>)</a>
            <a href="?filter=rejected" class="btn <?php echo $filter === 'rejected' ? 'btn-danger' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-times"></i> Refusées (<?php echo $countRejected; ?>)</a>
        </div>

        <!-- Liste des activités -->
        <div class="my-activities-list">
            <?php foreach ($myActivities as $act): ?>
            <div class="my-act-card">
                <div class="mac-left">
                    <div class="mac-icon mac-icon-<?php echo e($act['participation_status']); ?>">
                        <?php if ($act['participation_status'] === 'accepted'): ?>
                            <i class="fas fa-check-circle"></i>
                        <?php elseif ($act['participation_status'] === 'pending'): ?>
                            <i class="fas fa-hourglass-half"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mac-content">
                    <div class="mac-header">
                        <a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $act['id']; ?>" class="mac-title"><?php echo e($act['title']); ?></a>
                        <span class="badge badge-<?php echo e($act['status']); ?>"><?php echo e(ucfirst($act['status'])); ?></span>
                    </div>
                    <div class="mac-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($act['activity_date'])); ?></span>
                        <?php if ($act['location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo e($act['location']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-clock"></i> Demande: <?php echo timeAgo($act['request_date']); ?></span>
                    </div>
                    <div class="mac-status">
                        <?php if ($act['participation_status'] === 'accepted'): ?>
                            <span class="participation-badge p-accepted"><i class="fas fa-check-circle"></i> ✅ Participation confirmée</span>
                        <?php elseif ($act['participation_status'] === 'pending'): ?>
                            <span class="participation-badge p-pending"><i class="fas fa-hourglass-half"></i> ⏳ En attente de validation</span>
                            <form method="POST" style="display:inline;margin-left:8px;">
                                <input type="hidden" name="cancel_id" value="<?php echo $act['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Annuler cette demande ?')">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="participation-badge p-rejected"><i class="fas fa-times-circle"></i> ❌ Participation refusée</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if (empty($myActivities)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>Aucune activité</h3>
                <p>Vous n'avez pas encore participé à des activités.</p>
                <a href="<?php echo BASE_URL; ?>/user/activities.php" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Découvrir les activités</a>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination mt-3">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&filter=<?php echo e($filter); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                <?php else: ?><a href="?page=<?php echo $p; ?>&filter=<?php echo e($filter); ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&filter=<?php echo e($filter); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
