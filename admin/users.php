<?php
/**
 * Gestion des utilisateurs (Admin)
 * Fichier: admin/users.php
 * Fonctionnalités: liste, recherche, filtre, activer/désactiver, supprimer
 */
$pageTitle = 'Gestion des Utilisateurs';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);
    
    // Ne pas agir sur soi-même
    if ($targetId > 0 && $targetId != $_SESSION['user_id']) {
        if ($action === 'toggle_active') {
            $newStatus = (int)$_POST['new_status'];
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newStatus, $targetId]);
            $label = $newStatus ? 'activé' : 'désactivé';
            addLog('a ' . $label . ' le compte utilisateur #' . $targetId, 'user', $targetId);
            setFlash('success', 'Compte utilisateur ' . $label . ' !');
        }
        
        if ($action === 'change_role') {
            $newRole = $_POST['new_role'] ?? 'user';
            if (in_array($newRole, ['admin', 'user'])) {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $targetId]);
                addLog('a changé le rôle de l\'utilisateur #' . $targetId . ' en ' . $newRole, 'user', $targetId);
                setFlash('success', 'Rôle modifié !');
            }
        }
        
        if ($action === 'delete_user') {
            $userStmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $userStmt->execute([$targetId]);
            $userName = $userStmt->fetchColumn();
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
            addLog('a supprimé l\'utilisateur "' . $userName . '"', 'user', $targetId);
            setFlash('success', 'Utilisateur supprimé.');
        }
    } else if ($targetId == $_SESSION['user_id']) {
        setFlash('error', 'Vous ne pouvez pas modifier votre propre compte depuis cette page.');
    }
    
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit();
}

// Filtres
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = "WHERE 1=1";
$params = [];

if ($roleFilter && in_array($roleFilter, ['admin', 'user'])) {
    $where .= " AND role = ?";
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    if ($statusFilter === 'active') {
        $where .= " AND is_active = 1";
    } elseif ($statusFilter === 'inactive') {
        $where .= " AND is_active = 0";
    }
}
if ($search) {
    $where .= " AND (full_name LIKE ? OR email LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Pagination
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM users $where");
$totalStmt->execute($params);
$total = $totalStmt->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT u.*, 
    (SELECT COUNT(*) FROM participations WHERE user_id = u.id AND status = 'accepted') as activity_count
    FROM users u 
    $where 
    ORDER BY u.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Compteurs
$totalUsers = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch()['c'];
$totalAdmins = $pdo->query("SELECT COUNT(*) as c FROM users WHERE role = 'admin'")->fetch()['c'];
$totalActive = $pdo->query("SELECT COUNT(*) as c FROM users WHERE is_active = 1")->fetch()['c'];
$totalInactive = $pdo->query("SELECT COUNT(*) as c FROM users WHERE is_active = 0")->fetch()['c'];

// Vue détail utilisateur
$viewUser = null;
$viewUserActivities = [];
if (isset($_GET['view'])) {
    $viewId = (int)$_GET['view'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$viewId]);
    $viewUser = $stmt->fetch();
    
    if ($viewUser) {
        $stmt = $pdo->prepare("
            SELECT a.title, a.activity_date, p.status, p.created_at as joined_at
            FROM participations p 
            JOIN activities a ON p.activity_id = a.id 
            WHERE p.user_id = ? 
            ORDER BY p.created_at DESC LIMIT 10
        ");
        $stmt->execute([$viewId]);
        $viewUserActivities = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-users-cog"></i> Utilisateurs</h1>
                <p>Gérez les comptes utilisateurs de la plateforme</p>
            </div>
        </div>

        <!-- Stats rapides -->
        <div class="stats-grid mb-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Utilisateurs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
                <div class="stat-info">
                    <h3><?php echo $totalAdmins; ?></h3>
                    <p>Administrateurs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $totalActive; ?></h3>
                    <p>Actifs</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-user-slash"></i></div>
                <div class="stat-info">
                    <h3><?php echo $totalInactive; ?></h3>
                    <p>Désactivés</p>
                </div>
            </div>
        </div>

        <?php if ($viewUser): ?>
        <!-- Vue détail utilisateur -->
        <div class="table-card mb-3">
            <div class="table-header">
                <h3><i class="fas fa-user"></i> Profil de <?php echo e($viewUser['full_name']); ?></h3>
                <a href="<?php echo BASE_URL; ?>/admin/users.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
            </div>
            <div class="user-detail-grid" style="padding:24px;">
                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <label>Nom complet</label>
                        <p><?php echo e($viewUser['full_name']); ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Username</label>
                        <p><?php echo e($viewUser['username']); ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Email</label>
                        <p><?php echo e($viewUser['email']); ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Téléphone</label>
                        <p><?php echo e($viewUser['phone'] ?? 'Non renseigné'); ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Rôle</label>
                        <p><span class="badge badge-<?php echo $viewUser['role']; ?>"><?php echo ucfirst($viewUser['role']); ?></span></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Statut</label>
                        <p><?php echo $viewUser['is_active'] ? '<span class="badge badge-active">Actif</span>' : '<span class="badge badge-inactive">Désactivé</span>'; ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Inscrit le</label>
                        <p><?php echo date('d/m/Y H:i', strtotime($viewUser['created_at'])); ?></p>
                    </div>
                    <div class="profile-info-item">
                        <label>Bio</label>
                        <p><?php echo e($viewUser['bio'] ?? 'Aucune bio'); ?></p>
                    </div>
                </div>

                <!-- Activités de l'utilisateur -->
                <?php if (!empty($viewUserActivities)): ?>
                <h4 style="margin-top:24px;margin-bottom:12px;"><i class="fas fa-calendar-check"></i> Participations</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Activité</th>
                            <th>Date</th>
                            <th>Statut</th>
                            <th>Inscrit le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($viewUserActivities as $ua): ?>
                        <tr>
                            <td><?php echo e($ua['title']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($ua['activity_date'])); ?></td>
                            <td>
                                <?php if ($ua['status'] === 'accepted'): ?>
                                    <span class="badge badge-completed">Accepté</span>
                                <?php elseif ($ua['status'] === 'pending'): ?>
                                    <span class="badge badge-ongoing">En attente</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Refusé</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo timeAgo($ua['joined_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtres et recherche -->
        <div class="admin-filters-bar mb-3">
            <form method="GET" class="admin-search-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Rechercher un utilisateur..." value="<?php echo e($search); ?>">
                </div>
                <select name="role" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Tous les rôles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>Utilisateur</option>
                </select>
                <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Tous les statuts</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Actifs</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Désactivés</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i></button>
            </form>
        </div>

        <!-- Table des utilisateurs -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> utilisateur(s)</h3>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Activités</th>
                        <th>Inscrit le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr class="<?php echo !$u['is_active'] ? 'row-disabled' : ''; ?>">
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="table-avatar">
                                    <?php if ($u['avatar'] && $u['avatar'] !== 'default.png'): ?>
                                        <img src="<?php echo BASE_URL . '/assets/images/' . e($u['avatar']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong><?php echo e($u['full_name']); ?></strong>
                                    <br><small style="color:var(--text-muted);">@<?php echo e($u['username']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td><?php echo e($u['email']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $u['role']; ?>"><?php echo ucfirst($u['role']); ?></span>
                        </td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <span class="badge badge-active"><i class="fas fa-check"></i> Actif</span>
                            <?php else: ?>
                                <span class="badge badge-inactive"><i class="fas fa-ban"></i> Désactivé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="participant-count-inline"><i class="fas fa-calendar-check"></i> <?php echo $u['activity_count']; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="?view=<?php echo $u['id']; ?>" class="btn btn-icon btn-secondary" title="Voir profil">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                    <!-- Toggle actif/inactif -->
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?php echo $u['is_active'] ? '0' : '1'; ?>">
                                        <button type="submit" class="btn btn-icon <?php echo $u['is_active'] ? 'btn-warning' : 'btn-success'; ?>" 
                                                title="<?php echo $u['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                            <i class="fas <?php echo $u['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                        </button>
                                    </form>
                                    <!-- Supprimer -->
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cet utilisateur ? Cette action est irréversible.')">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:var(--text-muted);font-size:0.8rem;">Vous</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="8" class="text-center" style="padding:30px;color:var(--text-muted);">Aucun utilisateur trouvé</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&role=<?php echo e($roleFilter); ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
