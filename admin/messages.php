<?php
/**
 * Gestion des messages (Admin) v2.0
 * Fichier: admin/messages.php
 * Fonctionnalités: lu/non lu, détail, suppression, compteur
 */
$pageTitle = 'Messages';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Marquer comme lu
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $pdo->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/messages.php?view=' . $id);
    exit();
}

// Marquer comme non lu
if (isset($_GET['unread'])) {
    $id = (int)$_GET['unread'];
    $pdo->prepare("UPDATE contacts SET is_read = 0 WHERE id = ?")->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/messages.php');
    exit();
}

// Marquer tous comme lus
if (isset($_GET['readall'])) {
    $pdo->query("UPDATE contacts SET is_read = 1 WHERE is_read = 0");
    setFlash('success', 'Tous les messages ont été marqués comme lus.');
    header('Location: ' . BASE_URL . '/admin/messages.php');
    exit();
}

// Suppression
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM contacts WHERE id = ?")->execute([$id]);
    addLog('a supprimé un message de contact', 'contact', $id);
    setFlash('success', 'Message supprimé.');
    header('Location: ' . BASE_URL . '/admin/messages.php');
    exit();
}

// Voir un message
$viewMessage = null;
if (isset($_GET['view'])) {
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $viewMessage = $stmt->fetch();
    // Marquer comme lu
    if ($viewMessage && !$viewMessage['is_read']) {
        $pdo->prepare("UPDATE contacts SET is_read = 1 WHERE id = ?")->execute([$viewMessage['id']]);
        $viewMessage['is_read'] = 1;
    }
}

// Filtre
$filter = $_GET['filter'] ?? '';
$where = '';
if ($filter === 'unread') $where = 'WHERE is_read = 0';
if ($filter === 'read') $where = 'WHERE is_read = 1';

// Pagination
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->query("SELECT COUNT(*) as c FROM contacts $where")->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$messages = $pdo->query("SELECT * FROM contacts $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();

$unreadCount = $pdo->query("SELECT COUNT(*) as c FROM contacts WHERE is_read = 0")->fetch()['c'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-envelope"></i> Messages <?php if ($unreadCount > 0): ?><span class="header-badge"><?php echo $unreadCount; ?> non lu(s)</span><?php endif; ?></h1>
                <p>Gérez les messages reçus via le formulaire de contact</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <a href="?readall=1" class="btn btn-secondary"><i class="fas fa-check-double"></i> Tout marquer comme lu</a>
            <?php endif; ?>
        </div>

        <?php if ($viewMessage): ?>
        <!-- Vue détaillée du message -->
        <div class="message-detail-card">
            <div class="message-detail-header">
                <a href="<?php echo BASE_URL; ?>/admin/messages.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
                <div class="message-detail-actions">
                    <?php if ($viewMessage['is_read']): ?>
                        <a href="?unread=<?php echo $viewMessage['id']; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-envelope"></i> Marquer non lu</a>
                    <?php endif; ?>
                    <a href="?delete=<?php echo $viewMessage['id']; ?>" class="btn btn-sm btn-danger" data-confirm="Supprimer ce message ?"><i class="fas fa-trash"></i> Supprimer</a>
                </div>
            </div>
            <div class="message-detail-body">
                <h3><?php echo e($viewMessage['subject']); ?></h3>
                <div class="message-meta">
                    <span><i class="fas fa-user"></i> <?php echo e($viewMessage['name']); ?></span>
                    <span><i class="fas fa-envelope"></i> <?php echo e($viewMessage['email']); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y à H:i', strtotime($viewMessage['created_at'])); ?></span>
                </div>
                <div class="message-body-text">
                    <?php echo nl2br(e($viewMessage['message'])); ?>
                </div>
                <div class="message-reply-hint">
                    <a href="mailto:<?php echo e($viewMessage['email']); ?>?subject=Re: <?php echo e($viewMessage['subject']); ?>" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Répondre par email
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Filtres -->
        <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
            <a href="?filter=" class="btn <?php echo !$filter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Tous (<?php echo $total; ?>)</a>
            <a href="?filter=unread" class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-envelope"></i> Non lus</a>
            <a href="?filter=read" class="btn <?php echo $filter === 'read' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-envelope-open"></i> Lus</a>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> message(s)</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>De</th>
                        <th>Sujet</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                    <tr class="<?php echo !$msg['is_read'] ? 'row-unread' : ''; ?>">
                        <td>
                            <?php if (!$msg['is_read']): ?>
                                <span class="msg-dot"></span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo e($msg['name']); ?></strong><br><small style="color:var(--text-muted);"><?php echo e($msg['email']); ?></small></td>
                        <td><a href="?view=<?php echo $msg['id']; ?>" class="msg-subject-link"><?php echo e($msg['subject']); ?></a></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></td>
                        <td>
                            <?php if ($msg['is_read']): ?>
                                <span class="badge badge-completed"><i class="fas fa-check"></i> Lu</span>
                            <?php else: ?>
                                <span class="badge badge-ongoing"><i class="fas fa-circle"></i> Non lu</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?view=<?php echo $msg['id']; ?>" class="btn btn-icon btn-secondary" title="Voir"><i class="fas fa-eye"></i></a>
                                <a href="?delete=<?php echo $msg['id']; ?>" class="btn btn-icon btn-danger" data-confirm="Supprimer ce message ?" title="Supprimer"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($messages)): ?>
                    <tr><td colspan="6" class="text-center" style="padding:30px;color:var(--text-muted);">Aucun message</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&filter=<?php echo e($filter); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>&filter=<?php echo e($filter); ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&filter=<?php echo e($filter); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
