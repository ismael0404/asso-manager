<?php
/**
 * Gestion des activités (Admin) v3.0
 * Fichier: admin/activities.php
 * Ajouts: workflow participation (accept/reject), max_participants,
 *         registration_status, filtres avancés
 */
$pageTitle = 'Gestion des Activités';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $activityDate = $_POST['activity_date'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $status = $_POST['status'] ?? 'upcoming';
        $publicationStatus = $_POST['publication_status'] ?? 'published';
        $registrationStatus = $_POST['registration_status'] ?? 'open';
        $maxParticipants = !empty($_POST['max_participants']) ? (int)$_POST['max_participants'] : null;
        $imagePath = null;
        
        // Upload image
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['image'], 'activities');
            if ($upload['success']) {
                $imagePath = $upload['filename'];
            } else {
                setFlash('error', $upload['message']);
                header('Location: ' . BASE_URL . '/admin/activities.php');
                exit();
            }
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO activities (title, description, image, activity_date, location, status, publication_status, registration_status, max_participants, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $imagePath, $activityDate, $location, $status, $publicationStatus, $registrationStatus, $maxParticipants, $_SESSION['user_id']]);
            $newId = $pdo->lastInsertId();
            
            addLog('a ajouté l\'activité "' . $title . '"', 'activity', $newId);
            
            if ($publicationStatus === 'published') {
                notifyAllUsers('Nouvelle activité : ' . $title, '/user/activities.php?detail=' . $newId, 'activity');
            }
            
            setFlash('success', 'Activité ajoutée avec succès !');
        } else {
            $id = (int)$_POST['id'];
            if ($imagePath) {
                $stmt = $pdo->prepare("UPDATE activities SET title=?, description=?, image=?, activity_date=?, location=?, status=?, publication_status=?, registration_status=?, max_participants=? WHERE id=?");
                $stmt->execute([$title, $description, $imagePath, $activityDate, $location, $status, $publicationStatus, $registrationStatus, $maxParticipants, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE activities SET title=?, description=?, activity_date=?, location=?, status=?, publication_status=?, registration_status=?, max_participants=? WHERE id=?");
                $stmt->execute([$title, $description, $activityDate, $location, $status, $publicationStatus, $registrationStatus, $maxParticipants, $id]);
            }
            addLog('a modifié l\'activité "' . $title . '"', 'activity', $id);
            setFlash('success', 'Activité modifiée avec succès !');
        }
        header('Location: ' . BASE_URL . '/admin/activities.php');
        exit();
    }
    
    // Toggle publication
    if ($action === 'toggle_publish') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['new_status'] ?? 'published';
        $pdo->prepare("UPDATE activities SET publication_status=? WHERE id=?")->execute([$newStatus, $id]);
        $label = $newStatus === 'published' ? 'publiée' : 'mise en brouillon';
        addLog('a ' . $label . ' l\'activité #' . $id, 'activity', $id);
        setFlash('success', 'Activité ' . $label . ' !');
        header('Location: ' . BASE_URL . '/admin/activities.php');
        exit();
    }
    
    // Toggle registration
    if ($action === 'toggle_registration') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['new_reg_status'] ?? 'open';
        $pdo->prepare("UPDATE activities SET registration_status=? WHERE id=?")->execute([$newStatus, $id]);
        $label = $newStatus === 'open' ? 'ouvertes' : 'fermées';
        addLog('a ' . $label . ' les inscriptions pour l\'activité #' . $id, 'activity', $id);
        setFlash('success', 'Inscriptions ' . $label . ' !');
        header('Location: ' . BASE_URL . '/admin/activities.php');
        exit();
    }
    
    // Accepter/Refuser une participation
    if ($action === 'accept_participation' || $action === 'reject_participation') {
        $participationId = (int)($_POST['participation_id'] ?? 0);
        $newStatus = $action === 'accept_participation' ? 'accepted' : 'rejected';
        
        // Obtenir les infos de la participation
        $pStmt = $pdo->prepare("SELECT p.*, a.title, u.full_name FROM participations p JOIN activities a ON p.activity_id = a.id JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $pStmt->execute([$participationId]);
        $part = $pStmt->fetch();
        
        if ($part) {
            $pdo->prepare("UPDATE participations SET status = ? WHERE id = ?")->execute([$newStatus, $participationId]);
            
            // Notification à l'utilisateur
            if ($newStatus === 'accepted') {
                addNotification($part['user_id'], 'Votre participation à "' . $part['title'] . '" a été acceptée ! ✅', '/user/my-activities.php', 'participation');
                addLog('a accepté la participation de "' . $part['full_name'] . '" à "' . $part['title'] . '"', 'participation', $participationId);
            } else {
                addNotification($part['user_id'], 'Votre participation à "' . $part['title'] . '" a été refusée. ❌', '/user/my-activities.php', 'participation');
                addLog('a refusé la participation de "' . $part['full_name'] . '" à "' . $part['title'] . '"', 'participation', $participationId);
            }
            
            setFlash('success', 'Participation ' . ($newStatus === 'accepted' ? 'acceptée' : 'refusée') . '.');
        }
        
        $actId = $part['activity_id'] ?? 0;
        header('Location: ' . BASE_URL . '/admin/activities.php?participants=' . $actId);
        exit();
    }
    
    // Accepter/Refuser toutes les participations
    if ($action === 'accept_all' || $action === 'reject_all') {
        $actId = (int)($_POST['act_id'] ?? 0);
        $newStatus = $action === 'accept_all' ? 'accepted' : 'rejected';
        
        $pendingStmt = $pdo->prepare("SELECT p.id, p.user_id, a.title FROM participations p JOIN activities a ON p.activity_id = a.id WHERE p.activity_id = ? AND p.status = 'pending'");
        $pendingStmt->execute([$actId]);
        $pending = $pendingStmt->fetchAll();
        
        foreach ($pending as $p) {
            $pdo->prepare("UPDATE participations SET status = ? WHERE id = ?")->execute([$newStatus, $p['id']]);
            $emoji = $newStatus === 'accepted' ? '✅' : '❌';
            $label = $newStatus === 'accepted' ? 'acceptée' : 'refusée';
            addNotification($p['user_id'], 'Votre participation à "' . $p['title'] . '" a été ' . $label . '. ' . $emoji, '/user/my-activities.php', 'participation');
        }
        
        setFlash('success', count($pending) . ' participation(s) ' . ($newStatus === 'accepted' ? 'acceptée(s)' : 'refusée(s)') . '.');
        header('Location: ' . BASE_URL . '/admin/activities.php?participants=' . $actId);
        exit();
    }
}

// Suppression
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT title FROM activities WHERE id=?");
    $stmt->execute([$id]);
    $actTitle = $stmt->fetchColumn();
    $pdo->prepare("DELETE FROM activities WHERE id=?")->execute([$id]);
    addLog('a supprimé l\'activité "' . $actTitle . '"', 'activity', $id);
    setFlash('success', 'Activité supprimée.');
    header('Location: ' . BASE_URL . '/admin/activities.php');
    exit();
}

// Voir les participants (avec statuts)
$viewParticipants = null;
$participants = [];
$pendingCount = 0;
if (isset($_GET['participants'])) {
    $actId = (int)$_GET['participants'];
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id=?");
    $stmt->execute([$actId]);
    $viewParticipants = $stmt->fetch();
    
    if ($viewParticipants) {
        $partFilter = $_GET['pfilter'] ?? '';
        $pWhere = "WHERE p.activity_id = ?";
        $pParams = [$actId];
        if ($partFilter && in_array($partFilter, ['pending', 'accepted', 'rejected'])) {
            $pWhere .= " AND p.status = ?";
            $pParams[] = $partFilter;
        }
        
        $stmt = $pdo->prepare("SELECT u.id, u.full_name, u.email, u.phone, u.avatar, p.id as participation_id, p.status, p.created_at as joined_at 
                               FROM participations p 
                               JOIN users u ON p.user_id = u.id 
                               $pWhere 
                               ORDER BY p.created_at DESC");
        $stmt->execute($pParams);
        $participants = $stmt->fetchAll();
        
        $pendingCount = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE activity_id = ? AND status = 'pending'");
        $pendingCount->execute([$actId]);
        $pendingCount = $pendingCount->fetch()['c'];
    }
}

// Filtres pour la liste des activités
$filterStatus = $_GET['fstatus'] ?? '';
$filterDate = $_GET['fdate'] ?? '';
$sortBy = $_GET['sort'] ?? 'recent';

$where = "WHERE 1=1";
$params = [];
if ($filterStatus && in_array($filterStatus, ['upcoming', 'ongoing', 'completed'])) {
    $where .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$orderBy = "a.created_at DESC";
if ($sortBy === 'date') $orderBy = "a.activity_date DESC";
if ($sortBy === 'popular') $orderBy = "participant_count DESC";

// Pagination
$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->prepare("SELECT COUNT(*) as c FROM activities a $where");
$total->execute($params);
$total = $total->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$activities = $pdo->prepare("SELECT a.*, 
    (SELECT COUNT(*) FROM participations WHERE activity_id = a.id AND status = 'accepted') as participant_count,
    (SELECT COUNT(*) FROM participations WHERE activity_id = a.id AND status = 'pending') as pending_count
    FROM activities a $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$activities->execute($params);
$activities = $activities->fetchAll();

// Edit mode
$editActivity = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editActivity = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-calendar-alt"></i> Activités</h1>
                <p>Gérez les activités de l'association</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('activityModal')">
                <i class="fas fa-plus"></i> Nouvelle Activité
            </button>
        </div>

        <?php if ($viewParticipants): ?>
        <!-- Liste des participants avec gestion -->
        <div class="table-card mb-3">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> Participants : <?php echo e($viewParticipants['title']); ?> (<?php echo count($participants); ?>)
                    <?php if ($pendingCount > 0): ?>
                        <span class="header-badge"><?php echo $pendingCount; ?> en attente</span>
                    <?php endif; ?>
                </h3>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <a href="<?php echo BASE_URL; ?>/admin/activities.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
                    <?php if ($pendingCount > 0): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="accept_all">
                        <input type="hidden" name="act_id" value="<?php echo $viewParticipants['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Accepter toutes les demandes en attente ?')"><i class="fas fa-check-double"></i> Tout accepter</button>
                    </form>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="reject_all">
                        <input type="hidden" name="act_id" value="<?php echo $viewParticipants['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Refuser toutes les demandes en attente ?')"><i class="fas fa-times-circle"></i> Tout refuser</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filtres participants -->
            <div style="padding:12px 24px;display:flex;gap:6px;flex-wrap:wrap;">
                <a href="?participants=<?php echo $viewParticipants['id']; ?>&pfilter=" class="btn btn-sm <?php echo !isset($_GET['pfilter']) || $_GET['pfilter'] === '' ? 'btn-primary' : 'btn-secondary'; ?>">Tous</a>
                <a href="?participants=<?php echo $viewParticipants['id']; ?>&pfilter=pending" class="btn btn-sm <?php echo ($_GET['pfilter'] ?? '') === 'pending' ? 'btn-warning' : 'btn-secondary'; ?>"><i class="fas fa-hourglass-half"></i> En attente</a>
                <a href="?participants=<?php echo $viewParticipants['id']; ?>&pfilter=accepted" class="btn btn-sm <?php echo ($_GET['pfilter'] ?? '') === 'accepted' ? 'btn-success' : 'btn-secondary'; ?>"><i class="fas fa-check"></i> Acceptés</a>
                <a href="?participants=<?php echo $viewParticipants['id']; ?>&pfilter=rejected" class="btn btn-sm <?php echo ($_GET['pfilter'] ?? '') === 'rejected' ? 'btn-danger' : 'btn-secondary'; ?>"><i class="fas fa-times"></i> Refusés</a>
            </div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Inscrit le</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participants as $i => $p): ?>
                    <tr>
                        <td><?php echo $i + 1; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="table-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                                <strong><?php echo e($p['full_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo e($p['email']); ?></td>
                        <td><?php echo e($p['phone'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($p['joined_at'])); ?></td>
                        <td>
                            <?php if ($p['status'] === 'accepted'): ?>
                                <span class="badge badge-completed"><i class="fas fa-check"></i> Accepté</span>
                            <?php elseif ($p['status'] === 'pending'): ?>
                                <span class="badge badge-ongoing"><i class="fas fa-hourglass-half"></i> En attente</span>
                            <?php else: ?>
                                <span class="badge badge-inactive"><i class="fas fa-times"></i> Refusé</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="accept_participation">
                                        <input type="hidden" name="participation_id" value="<?php echo $p['participation_id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-success" title="Accepter"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_participation">
                                        <input type="hidden" name="participation_id" value="<?php echo $p['participation_id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-danger" title="Refuser"><i class="fas fa-times"></i></button>
                                    </form>
                                <?php elseif ($p['status'] === 'accepted'): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reject_participation">
                                        <input type="hidden" name="participation_id" value="<?php echo $p['participation_id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-danger" title="Révoquer"><i class="fas fa-user-times"></i></button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="accept_participation">
                                        <input type="hidden" name="participation_id" value="<?php echo $p['participation_id']; ?>">
                                        <button type="submit" class="btn btn-icon btn-success" title="Accepter"><i class="fas fa-user-check"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($participants)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">Aucun participant</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Filtres activités -->
        <div class="admin-filters-bar mb-2">
            <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                <a href="?fstatus=&sort=<?php echo e($sortBy); ?>" class="btn <?php echo !$filterStatus ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Toutes</a>
                <a href="?fstatus=upcoming&sort=<?php echo e($sortBy); ?>" class="btn <?php echo $filterStatus === 'upcoming' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-clock"></i> À venir</a>
                <a href="?fstatus=ongoing&sort=<?php echo e($sortBy); ?>" class="btn <?php echo $filterStatus === 'ongoing' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-play"></i> En cours</a>
                <a href="?fstatus=completed&sort=<?php echo e($sortBy); ?>" class="btn <?php echo $filterStatus === 'completed' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-check"></i> Terminées</a>
                <span style="color:var(--text-muted);margin:0 8px;">|</span>
                <a href="?fstatus=<?php echo e($filterStatus); ?>&sort=recent" class="btn <?php echo $sortBy === 'recent' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Récentes</a>
                <a href="?fstatus=<?php echo e($filterStatus); ?>&sort=date" class="btn <?php echo $sortBy === 'date' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Par date</a>
                <a href="?fstatus=<?php echo e($filterStatus); ?>&sort=popular" class="btn <?php echo $sortBy === 'popular' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-fire"></i> Populaires</a>
            </div>
        </div>

        <!-- Table des activités -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> activité(s) au total</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Titre</th>
                        <th>Date</th>
                        <th>Lieu</th>
                        <th>Statut</th>
                        <th>Publication</th>
                        <th>Inscriptions</th>
                        <th>Participants</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $i => $act): ?>
                    <tr>
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td><strong><?php echo e($act['title']); ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($act['activity_date'])); ?></td>
                        <td><?php echo e($act['location'] ?? '-'); ?></td>
                        <td><span class="badge badge-<?php echo e($act['status']); ?>"><?php echo e(ucfirst($act['status'])); ?></span></td>
                        <td>
                            <?php if (($act['publication_status'] ?? 'published') === 'published'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_publish">
                                    <input type="hidden" name="id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="new_status" value="draft">
                                    <button type="submit" class="badge badge-active btn-badge" title="Mettre en brouillon"><i class="fas fa-eye"></i> Publié</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_publish">
                                    <input type="hidden" name="id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="new_status" value="published">
                                    <button type="submit" class="badge badge-inactive btn-badge" title="Publier"><i class="fas fa-eye-slash"></i> Brouillon</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (($act['registration_status'] ?? 'open') === 'open'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_registration">
                                    <input type="hidden" name="id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="new_reg_status" value="closed">
                                    <button type="submit" class="badge badge-active btn-badge" title="Fermer les inscriptions"><i class="fas fa-lock-open"></i> Ouvert</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_registration">
                                    <input type="hidden" name="id" value="<?php echo $act['id']; ?>">
                                    <input type="hidden" name="new_reg_status" value="open">
                                    <button type="submit" class="badge badge-inactive btn-badge" title="Ouvrir les inscriptions"><i class="fas fa-lock"></i> Fermé</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?participants=<?php echo $act['id']; ?>" class="participant-count" title="Voir les participants">
                                <i class="fas fa-users"></i> <?php echo $act['participant_count']; ?>
                                <?php if ($act['max_participants']): ?>/<?php echo $act['max_participants']; ?><?php endif; ?>
                                <?php if ($act['pending_count'] > 0): ?>
                                    <span class="pending-badge"><?php echo $act['pending_count']; ?> ⏳</span>
                                <?php endif; ?>
                            </a>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?participants=<?php echo $act['id']; ?>" class="btn btn-icon btn-secondary" title="Participants">
                                    <i class="fas fa-users"></i>
                                </a>
                                <a href="?edit=<?php echo $act['id']; ?>" class="btn btn-icon btn-warning" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?delete=<?php echo $act['id']; ?>" class="btn btn-icon btn-danger" data-confirm="Supprimer cette activité ?" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($activities)): ?>
                    <tr><td colspan="9" class="text-center" style="padding:30px;color:var(--text-muted);">Aucune activité trouvée</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&fstatus=<?php echo e($filterStatus); ?>&sort=<?php echo e($sortBy); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>&fstatus=<?php echo e($filterStatus); ?>&sort=<?php echo e($sortBy); ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&fstatus=<?php echo e($filterStatus); ?>&sort=<?php echo e($sortBy); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Ajout/Modification -->
<div class="modal-overlay <?php echo $editActivity ? 'show' : ''; ?>" id="activityModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editActivity ? 'Modifier l\'Activité' : 'Nouvelle Activité'; ?></h3>
            <button class="modal-close" onclick="closeModal('activityModal'); <?php if($editActivity) echo 'window.location.href=\'activities.php\''; ?>">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?php echo $editActivity ? 'edit' : 'add'; ?>">
                <?php if ($editActivity): ?>
                    <input type="hidden" name="id" value="<?php echo $editActivity['id']; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" required
                           value="<?php echo e($editActivity['title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" required><?php echo e($editActivity['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="activity_date" class="form-control" required
                               value="<?php echo e($editActivity['activity_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <option value="upcoming" <?php echo ($editActivity['status'] ?? '') === 'upcoming' ? 'selected' : ''; ?>>À venir</option>
                            <option value="ongoing" <?php echo ($editActivity['status'] ?? '') === 'ongoing' ? 'selected' : ''; ?>>En cours</option>
                            <option value="completed" <?php echo ($editActivity['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Terminée</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Lieu</label>
                        <input type="text" name="location" class="form-control" placeholder="ex: Abidjan"
                               value="<?php echo e($editActivity['location'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Publication</label>
                        <select name="publication_status" class="form-control">
                            <option value="published" <?php echo ($editActivity['publication_status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Publié</option>
                            <option value="draft" <?php echo ($editActivity['publication_status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Inscriptions</label>
                        <select name="registration_status" class="form-control">
                            <option value="open" <?php echo ($editActivity['registration_status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>Ouvertes</option>
                            <option value="closed" <?php echo ($editActivity['registration_status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Fermées</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Limite de places (optionnel)</label>
                        <input type="number" name="max_participants" class="form-control" min="1" placeholder="Illimité"
                               value="<?php echo e($editActivity['max_participants'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Image (optionnel)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('activityModal'); <?php if($editActivity) echo 'window.location.href=\'activities.php\''; ?>">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> <?php echo $editActivity ? 'Modifier' : 'Ajouter'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
