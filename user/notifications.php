<?php
/**
 * Notifications utilisateur v3.0
 * Fichier: user/notifications.php
 * Ajout: icônes par type, filtrage par type
 */
$pageTitle = 'Notifications';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Marquer comme lu
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
    // Rediriger vers le lien si disponible
    $linkStmt = $pdo->prepare("SELECT link FROM notifications WHERE id = ? AND user_id = ?");
    $linkStmt->execute([$id, $_SESSION['user_id']]);
    $notifLink = $linkStmt->fetchColumn();
    if ($notifLink) {
        header('Location: ' . BASE_URL . $notifLink);
    } else {
        header('Location: ' . BASE_URL . '/user/notifications.php');
    }
    exit();
}

// Marquer toutes comme lues
if (isset($_GET['readall'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$_SESSION['user_id']]);
    setFlash('success', 'Toutes les notifications ont été marquées comme lues.');
    header('Location: ' . BASE_URL . '/user/notifications.php');
    exit();
}

// Supprimer une notification
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?")->execute([$id, $_SESSION['user_id']]);
    header('Location: ' . BASE_URL . '/user/notifications.php');
    exit();
}

// Supprimer toutes les notifications lues
if (isset($_GET['clearread'])) {
    $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1")->execute([$_SESSION['user_id']]);
    setFlash('success', 'Notifications lues supprimées.');
    header('Location: ' . BASE_URL . '/user/notifications.php');
    exit();
}

// Filtre par type
$typeFilter = $_GET['type'] ?? '';
$filterWhere = '';
$filterParams = [$_SESSION['user_id']];
if ($typeFilter && in_array($typeFilter, ['activity', 'participation', 'comment', 'message', 'general'])) {
    $filterWhere = " AND type = ?";
    $filterParams[] = $typeFilter;
}

// Pagination
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ?" . $filterWhere);
$total->execute($filterParams);
$totalCount = $total->fetch()['c'];
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ?" . $filterWhere . " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($filterParams);
$notifications = $stmt->fetchAll();

$unreadCount = getUnreadNotifCount();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-bell"></i> Mes <span>Notifications</span></h1>
        <p>Restez informé des dernières actualités de l'association</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:800px;">
        <div class="notif-header-bar">
            <h3><?php echo $totalCount; ?> notification(s) <?php if ($unreadCount > 0): ?><span class="header-badge"><?php echo $unreadCount; ?> non lue(s)</span><?php endif; ?></h3>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <?php if ($unreadCount > 0): ?>
                    <a href="?readall=1" class="btn btn-sm btn-secondary"><i class="fas fa-check-double"></i> Tout marquer comme lu</a>
                <?php endif; ?>
                <a href="?clearread=1" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer les notifications lues ?')"><i class="fas fa-trash"></i> Vider les lues</a>
            </div>
        </div>

        <!-- Filtres par type -->
        <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;">
            <a href="?type=" class="btn <?php echo !$typeFilter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Toutes</a>
            <a href="?type=activity" class="btn <?php echo $typeFilter === 'activity' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-calendar-plus"></i> Activités</a>
            <a href="?type=participation" class="btn <?php echo $typeFilter === 'participation' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-hand-paper"></i> Participations</a>
            <a href="?type=comment" class="btn <?php echo $typeFilter === 'comment' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-comment"></i> Commentaires</a>
            <a href="?type=message" class="btn <?php echo $typeFilter === 'message' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-envelope"></i> Messages</a>
        </div>

        <div class="notif-list">
            <?php foreach ($notifications as $notif): ?>
            <div class="notif-item <?php echo !$notif['is_read'] ? 'notif-unread' : ''; ?>">
                <div class="notif-icon-wrap <?php echo !$notif['is_read'] ? '' : 'notif-icon-read'; ?>">
                    <i class="fas <?php echo getNotifIcon($notif['type'] ?? 'general'); ?>"></i>
                </div>
                <div class="notif-body">
                    <p class="notif-message"><?php echo e($notif['message']); ?></p>
                    <div class="notif-meta-bar">
                        <span class="notif-time"><i class="fas fa-clock"></i> <?php echo timeAgo($notif['created_at']); ?></span>
                        <?php if ($notif['type']): ?>
                            <span class="notif-type-badge"><?php echo e(ucfirst($notif['type'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="notif-actions">
                    <?php if (!$notif['is_read']): ?>
                        <a href="?read=<?php echo $notif['id']; ?>" class="btn btn-icon btn-secondary" title="Marquer comme lu"><i class="fas fa-check"></i></a>
                    <?php endif; ?>
                    <?php if ($notif['link']): ?>
                        <a href="<?php echo BASE_URL . e($notif['link']); ?>" class="btn btn-icon btn-primary" title="Voir"><i class="fas fa-external-link-alt"></i></a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $notif['id']; ?>" class="btn btn-icon btn-danger" title="Supprimer"><i class="fas fa-times"></i></a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>Aucune notification</h3>
                <p>Vous n'avez aucune notification pour le moment.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination mt-3">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&type=<?php echo e($typeFilter); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                <?php else: ?><a href="?page=<?php echo $p; ?>&type=<?php echo e($typeFilter); ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&type=<?php echo e($typeFilter); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
