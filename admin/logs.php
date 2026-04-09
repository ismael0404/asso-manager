<?php
/**
 * Historique des actions (Admin)
 * Fichier: admin/logs.php
 */
$pageTitle = 'Historique des Actions';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Pagination
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$filterType = $_GET['type'] ?? '';

$where = '';
$params = [];
if ($filterType && in_array($filterType, ['activity', 'post', 'member', 'participation'])) {
    $where = 'WHERE l.entity_type = ?';
    $params[] = $filterType;
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM logs l $where");
$totalStmt->execute($params);
$total = $totalStmt->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT l.*, u.full_name 
                        FROM logs l 
                        LEFT JOIN users u ON l.user_id = u.id 
                        $where 
                        ORDER BY l.created_at DESC 
                        LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-history"></i> Historique des Actions</h1>
                <p>Consultez l'historique de toutes les actions effectuées</p>
            </div>
        </div>

        <!-- Filtres -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
            <a href="?type=" class="btn <?php echo !$filterType ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                <i class="fas fa-list"></i> Toutes
            </a>
            <a href="?type=activity" class="btn <?php echo $filterType === 'activity' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                <i class="fas fa-calendar"></i> Activités
            </a>
            <a href="?type=post" class="btn <?php echo $filterType === 'post' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                <i class="fas fa-newspaper"></i> Publications
            </a>
            <a href="?type=member" class="btn <?php echo $filterType === 'member' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">
                <i class="fas fa-users"></i> Membres
            </a>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> action(s) enregistrée(s)</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
            </div>

            <div class="log-list-full">
                <?php foreach ($logs as $log): ?>
                <div class="log-item-full">
                    <div class="log-icon-full">
                        <?php
                        $icon = 'fas fa-info-circle';
                        $iconClass = 'blue';
                        switch ($log['entity_type'] ?? '') {
                            case 'activity': $icon = 'fas fa-calendar'; $iconClass = 'blue'; break;
                            case 'post': $icon = 'fas fa-newspaper'; $iconClass = 'purple'; break;
                            case 'member': $icon = 'fas fa-user'; $iconClass = 'green'; break;
                            case 'participation': $icon = 'fas fa-hand-paper'; $iconClass = 'orange'; break;
                        }
                        ?>
                        <div class="stat-icon <?php echo $iconClass; ?>" style="width:42px;height:42px;font-size:1rem;">
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                    </div>
                    <div class="log-content-full">
                        <p>
                            <strong><?php echo e($log['full_name'] ?? 'Système'); ?></strong>
                            <?php echo e($log['action']); ?>
                        </p>
                        <div class="log-meta">
                            <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y à H:i', strtotime($log['created_at'])); ?></span>
                            <span><?php echo timeAgo($log['created_at']); ?></span>
                            <?php if ($log['entity_type']): ?>
                                <span class="badge badge-upcoming"><?php echo e(ucfirst($log['entity_type'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                <div class="empty-state" style="padding:40px;">
                    <i class="fas fa-history"></i>
                    <h3>Aucune action enregistrée</h3>
                    <p>Les actions apparaîtront ici au fur et à mesure.</p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&type=<?php echo e($filterType); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>&type=<?php echo e($filterType); ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&type=<?php echo e($filterType); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
