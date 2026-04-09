<?php
/**
 * Activités côté utilisateur v3.0
 * Fichier: user/activities.php
 * Ajouts: participation avec workflow (pending/accepted/rejected),
 *         favoris, commentaires nested, vérification places
 */
$pageTitle = 'Activités';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $action = $_POST['action'] ?? '';
    $activityId = (int)($_POST['activity_id'] ?? 0);
    
    // Demande de participation (statut = pending)
    if ($action === 'participate' && $activityId > 0) {
        // Vérifier si activité existe et accepte les inscriptions
        $actStmt = $pdo->prepare("SELECT * FROM activities WHERE id = ? AND publication_status = 'published'");
        $actStmt->execute([$activityId]);
        $activity = $actStmt->fetch();
        
        if ($activity && canRegister($activity)) {
            $check = $pdo->prepare("SELECT id FROM participations WHERE user_id = ? AND activity_id = ?");
            $check->execute([$_SESSION['user_id'], $activityId]);
            if (!$check->fetch()) {
                $pdo->prepare("INSERT INTO participations (user_id, activity_id, status) VALUES (?, ?, 'pending')")
                    ->execute([$_SESSION['user_id'], $activityId]);
                
                $userName = $_SESSION['user_name'] ?? 'Un utilisateur';
                
                // Notifier les admins
                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                foreach ($admins as $admin) {
                    addNotification($admin['id'], $userName . ' demande à participer à "' . $activity['title'] . '"', '/admin/activities.php?participants=' . $activityId, 'participation');
                }
                
                addLog('a demandé à participer à l\'activité "' . $activity['title'] . '"', 'participation', $activityId);
                setFlash('success', 'Votre demande de participation a été envoyée ! En attente de validation par l\'administrateur.');
            } else {
                setFlash('error', 'Vous avez déjà une demande pour cette activité.');
            }
        } else {
            setFlash('error', 'Cette activité n\'accepte plus les inscriptions.');
        }
        header('Location: ' . BASE_URL . '/user/activities.php?detail=' . $activityId);
        exit();
    }
    
    // Annuler participation (seulement si pending)
    if ($action === 'cancel_participation' && $activityId > 0) {
        $pdo->prepare("DELETE FROM participations WHERE user_id = ? AND activity_id = ? AND status = 'pending'")
            ->execute([$_SESSION['user_id'], $activityId]);
        addLog('a annulé sa demande de participation à l\'activité #' . $activityId, 'participation', $activityId);
        setFlash('success', 'Votre demande de participation a été annulée.');
        header('Location: ' . BASE_URL . '/user/activities.php?detail=' . $activityId);
        exit();
    }
    
    // Toggle favori
    if ($action === 'toggle_favorite' && $activityId > 0) {
        $check = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND activity_id = ?");
        $check->execute([$_SESSION['user_id'], $activityId]);
        if ($check->fetch()) {
            $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND activity_id = ?")->execute([$_SESSION['user_id'], $activityId]);
            setFlash('success', 'Activité retirée des favoris.');
        } else {
            $pdo->prepare("INSERT INTO favorites (user_id, activity_id) VALUES (?, ?)")->execute([$_SESSION['user_id'], $activityId]);
            setFlash('success', 'Activité ajoutée aux favoris !');
        }
        header('Location: ' . BASE_URL . '/user/activities.php?detail=' . $activityId);
        exit();
    }
    
    // Ajouter commentaire (avec support parent_id pour réponses)
    if ($action === 'add_comment' && $activityId > 0) {
        $content = trim($_POST['comment_content'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO comments (user_id, activity_id, parent_id, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $activityId, $parentId, $content]);
            
            // Notifier l'auteur du commentaire parent (si réponse)
            if ($parentId) {
                $parentComment = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
                $parentComment->execute([$parentId]);
                $parentUser = $parentComment->fetch();
                if ($parentUser && $parentUser['user_id'] != $_SESSION['user_id']) {
                    $userName = $_SESSION['user_name'] ?? 'Un utilisateur';
                    addNotification($parentUser['user_id'], $userName . ' a répondu à votre commentaire', '/user/activities.php?detail=' . $activityId . '#comments', 'comment');
                }
            }
            
            setFlash('success', 'Commentaire ajouté !');
        }
        header('Location: ' . BASE_URL . '/user/activities.php?detail=' . $activityId . '#comments');
        exit();
    }
    
    // Supprimer commentaire
    if ($action === 'delete_comment') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?")->execute([$commentId, $_SESSION['user_id']]);
        setFlash('success', 'Commentaire supprimé.');
        header('Location: ' . BASE_URL . '/user/activities.php?detail=' . $activityId . '#comments');
        exit();
    }
}

// Vue détaillée d'une activité
$detailActivity = null;
$activityComments = [];
$participantCount = 0;
$acceptedCount = 0;
$participationStatus = null;
$isFav = false;

if (isset($_GET['detail'])) {
    $detailId = (int)$_GET['detail'];
    $stmt = $pdo->prepare("SELECT * FROM activities WHERE id = ? AND publication_status = 'published'");
    $stmt->execute([$detailId]);
    $detailActivity = $stmt->fetch();
    
    if ($detailActivity) {
        // Nombre de participants acceptés
        $acceptedCount = getAcceptedParticipantCount($detailId);
        
        // Tous les participants (toutes statuts)
        $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE activity_id = ?");
        $stmt->execute([$detailId]);
        $participantCount = $stmt->fetch()['c'];
        
        // Statut de participation de l'utilisateur
        if (isLoggedIn()) {
            $participationStatus = getParticipationStatus($detailId);
            $isFav = isFavorite($detailId);
        }
        
        // Commentaires avec réponses (nested) - top-level first
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name, u.avatar 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.activity_id = ? AND c.parent_id IS NULL 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$detailId]);
        $activityComments = $stmt->fetchAll();
        
        // Charger les réponses pour chaque commentaire
        foreach ($activityComments as &$comment) {
            $repliesStmt = $pdo->prepare("
                SELECT c.*, u.full_name, u.avatar 
                FROM comments c 
                JOIN users u ON c.user_id = u.id 
                WHERE c.parent_id = ? 
                ORDER BY c.created_at ASC
            ");
            $repliesStmt->execute([$comment['id']]);
            $comment['replies'] = $repliesStmt->fetchAll();
        }
        unset($comment);
        
        // Liste des participants acceptés (visible)
        $participantsStmt = $pdo->prepare("
            SELECT u.full_name, u.avatar 
            FROM participations p 
            JOIN users u ON p.user_id = u.id 
            WHERE p.activity_id = ? AND p.status = 'accepted' 
            ORDER BY p.created_at DESC LIMIT 10
        ");
        $participantsStmt->execute([$detailId]);
        $visibleParticipants = $participantsStmt->fetchAll();
    }
}

// Pagination pour la liste
$perPage = 9;
$page = max(1, (int)($_GET['page'] ?? 1));
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$where = "WHERE publication_status = 'published'";
$params = [];
if ($statusFilter && in_array($statusFilter, ['upcoming', 'ongoing', 'completed'])) {
    $where .= ' AND status = ?';
    $params[] = $statusFilter;
}
if ($search) {
    $where .= ' AND (title LIKE ? OR description LIKE ? OR location LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM activities $where");
$totalStmt->execute($params);
$total = $totalStmt->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT a.*, 
    (SELECT COUNT(*) FROM participations WHERE activity_id = a.id AND status = 'accepted') as participant_count
    FROM activities a $where ORDER BY activity_date DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$activities = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Page Header -->
<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1>Toutes nos <span>Activités</span></h1>
        <p>Découvrez l'ensemble des activités passées, en cours et à venir de notre association.</p>
    </div>
</section>

<?php if ($detailActivity): ?>
<!-- Vue détaillée -->
<section class="section">
    <div class="container" style="max-width:900px;">
        <a href="<?php echo BASE_URL; ?>/user/activities.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Retour aux activités</a>
        
        <div class="activity-detail-card">
            <?php if ($detailActivity['image']): ?>
            <div class="detail-img">
                <img src="<?php echo BASE_URL . '/assets/images/' . e($detailActivity['image']); ?>" alt="<?php echo e($detailActivity['title']); ?>">
            </div>
            <?php endif; ?>
            
            <div class="detail-body">
                <div class="detail-badges">
                    <span class="badge badge-<?php echo e($detailActivity['status']); ?>"><?php echo e(ucfirst($detailActivity['status'])); ?></span>
                    <?php if ($detailActivity['registration_status'] === 'closed'): ?>
                        <span class="badge badge-inactive"><i class="fas fa-lock"></i> Inscriptions fermées</span>
                    <?php else: ?>
                        <span class="badge badge-active"><i class="fas fa-lock-open"></i> Inscriptions ouvertes</span>
                    <?php endif; ?>
                    <span class="participant-count-inline"><i class="fas fa-users"></i> <?php echo $acceptedCount; ?> participant(s)
                        <?php if ($detailActivity['max_participants']): ?>
                            / <?php echo $detailActivity['max_participants']; ?> places
                        <?php endif; ?>
                    </span>
                </div>
                
                <h2><?php echo e($detailActivity['title']); ?></h2>
                
                <div class="detail-meta">
                    <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($detailActivity['activity_date'])); ?></span>
                    <?php if ($detailActivity['location']): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo e($detailActivity['location']); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="detail-description">
                    <?php echo nl2br(e($detailActivity['description'])); ?>
                </div>
                
                <!-- Boutons Participer / Favori -->
                <div class="detail-actions-bar">
                    <?php if (isLoggedIn()): ?>
                        <!-- Bouton Favori -->
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_favorite">
                            <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                            <button type="submit" class="btn <?php echo $isFav ? 'btn-danger' : 'btn-secondary'; ?>" title="<?php echo $isFav ? 'Retirer des favoris' : 'Ajouter aux favoris'; ?>">
                                <i class="fas fa-heart"></i> <?php echo $isFav ? 'Favori' : 'Ajouter aux favoris'; ?>
                            </button>
                        </form>
                        
                        <!-- Bouton Participation -->
                        <?php if ($participationStatus === null): ?>
                            <?php if (canRegister($detailActivity)): ?>
                                <form method="POST" class="participation-form" style="display:inline;">
                                    <input type="hidden" name="action" value="participate">
                                    <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="fas fa-hand-paper"></i> Participer
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="participation-status completed-status"><i class="fas fa-ban"></i> Inscriptions non disponibles</span>
                            <?php endif; ?>
                        <?php elseif ($participationStatus === 'pending'): ?>
                            <div class="participation-status-box pending-box">
                                <span class="participation-status-label"><i class="fas fa-hourglass-half"></i> ⏳ Demande en attente de validation</span>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="cancel_participation">
                                    <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-times"></i> Annuler ma demande
                                    </button>
                                </form>
                            </div>
                        <?php elseif ($participationStatus === 'accepted'): ?>
                            <div class="participation-status-box accepted-box">
                                <span class="participation-status-label"><i class="fas fa-check-circle"></i> ✅ Participation confirmée</span>
                            </div>
                        <?php elseif ($participationStatus === 'rejected'): ?>
                            <div class="participation-status-box rejected-box">
                                <span class="participation-status-label"><i class="fas fa-times-circle"></i> ❌ Participation refusée</span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-lg"><i class="fas fa-sign-in-alt"></i> Connectez-vous pour participer</a>
                    <?php endif; ?>
                </div>
                
                <!-- Participants visibles -->
                <?php if (!empty($visibleParticipants)): ?>
                <div class="participants-preview mt-3">
                    <h4><i class="fas fa-users"></i> Participants confirmés</h4>
                    <div class="participants-avatars">
                        <?php foreach ($visibleParticipants as $p): ?>
                            <div class="participant-chip">
                                <i class="fas fa-user"></i>
                                <span><?php echo e($p['full_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($acceptedCount > 10): ?>
                            <span class="participant-more">+<?php echo $acceptedCount - 10; ?> autres</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Commentaires -->
        <div class="comments-section" id="comments">
            <h3><i class="fas fa-comments"></i> Commentaires (<?php echo count($activityComments); ?>)</h3>
            
            <?php if (isLoggedIn()): ?>
            <form method="POST" class="comment-form">
                <input type="hidden" name="action" value="add_comment">
                <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                <div class="comment-input-group">
                    <textarea name="comment_content" class="form-control" placeholder="Écrire un commentaire..." required style="min-height:80px;"></textarea>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publier</button>
                </div>
            </form>
            <?php endif; ?>
            
            <div class="comments-list">
                <?php foreach ($activityComments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-avatar">
                        <?php if ($comment['avatar'] && $comment['avatar'] !== 'default.png'): ?>
                            <img src="<?php echo BASE_URL . '/assets/images/' . e($comment['avatar']); ?>" alt="Avatar" class="comment-avatar-img">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="comment-body">
                        <div class="comment-header">
                            <strong><?php echo e($comment['full_name']); ?></strong>
                            <span class="comment-time"><?php echo timeAgo($comment['created_at']); ?></span>
                        </div>
                        <p class="comment-text"><?php echo nl2br(e($comment['content'])); ?></p>
                        <div class="comment-actions-bar">
                            <?php if (isLoggedIn()): ?>
                                <button class="comment-reply-btn" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">
                                    <i class="fas fa-reply"></i> Répondre
                                </button>
                            <?php endif; ?>
                            <?php if (isLoggedIn() && $comment['user_id'] == $_SESSION['user_id']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete_comment">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                                <button type="submit" class="comment-delete-btn" title="Supprimer"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formulaire de réponse (caché) -->
                        <?php if (isLoggedIn()): ?>
                        <div class="reply-form-wrapper" id="replyForm-<?php echo $comment['id']; ?>" style="display:none;">
                            <form method="POST" class="reply-form">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                                <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                <div class="reply-input-group">
                                    <textarea name="comment_content" class="form-control" placeholder="Écrire une réponse..." required style="min-height:60px;"></textarea>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-reply"></i> Répondre</button>
                                </div>
                            </form>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Réponses nested -->
                        <?php if (!empty($comment['replies'])): ?>
                        <div class="comment-replies">
                            <?php foreach ($comment['replies'] as $reply): ?>
                            <div class="comment-item comment-reply">
                                <div class="comment-avatar comment-avatar-sm">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="comment-body">
                                    <div class="comment-header">
                                        <strong><?php echo e($reply['full_name']); ?></strong>
                                        <span class="comment-time"><?php echo timeAgo($reply['created_at']); ?></span>
                                    </div>
                                    <p class="comment-text"><?php echo nl2br(e($reply['content'])); ?></p>
                                    <?php if (isLoggedIn() && $reply['user_id'] == $_SESSION['user_id']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_comment">
                                        <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                        <input type="hidden" name="activity_id" value="<?php echo $detailActivity['id']; ?>">
                                        <button type="submit" class="comment-delete-btn" title="Supprimer"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($activityComments)): ?>
                <div class="empty-state" style="padding:30px;">
                    <i class="fas fa-comments" style="font-size:2rem;"></i>
                    <p>Aucun commentaire. Soyez le premier à commenter !</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php else: ?>
<!-- Liste des activités -->
<section class="section">
    <div class="container">
        <!-- Recherche + Filtres -->
        <div class="activity-filters-bar">
            <form method="GET" class="activity-search-form">
                <div class="search-box" style="flex:1;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="q" placeholder="Rechercher une activité..." value="<?php echo e($search); ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Rechercher</button>
            </form>
            <div class="activity-filter-buttons">
                <a href="?status=<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="btn <?php echo !$statusFilter ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Toutes</a>
                <a href="?status=upcoming<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="btn <?php echo $statusFilter === 'upcoming' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-clock"></i> À venir</a>
                <a href="?status=ongoing<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="btn <?php echo $statusFilter === 'ongoing' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-play"></i> En cours</a>
                <a href="?status=completed<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="btn <?php echo $statusFilter === 'completed' ? 'btn-primary' : 'btn-secondary'; ?> btn-sm"><i class="fas fa-check"></i> Terminées</a>
            </div>
        </div>
        
        <?php if (count($activities) > 0): ?>
        <div class="grid-3">
            <?php foreach ($activities as $activity): ?>
            <div class="card card-clickable" onclick="window.location='?detail=<?php echo $activity['id']; ?>'">
                <div class="card-img">
                    <?php if ($activity['image']): ?>
                        <img src="<?php echo BASE_URL . '/assets/images/' . e($activity['image']); ?>" alt="<?php echo e($activity['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-calendar-check"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="mb-1" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <span class="badge badge-<?php echo e($activity['status']); ?>">
                            <?php echo e(ucfirst($activity['status'])); ?>
                        </span>
                        <span class="participant-count-small"><i class="fas fa-users"></i> <?php echo $activity['participant_count']; ?></span>
                        <?php if ($activity['max_participants']): ?>
                            <span class="participant-count-small"><i class="fas fa-chair"></i> <?php echo $activity['max_participants']; ?> max</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="card-title"><?php echo e($activity['title']); ?></h3>
                    <p class="card-text"><?php echo e(mb_strimwidth($activity['description'], 0, 150, '...')); ?></p>
                    <div class="card-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($activity['activity_date'])); ?></span>
                        <?php if ($activity['location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo e(mb_strimwidth($activity['location'], 0, 30, '...')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <span class="btn btn-sm btn-primary" onclick="event.stopPropagation();">
                        <i class="fas fa-eye"></i> Détails
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination mt-4">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                <?php else: ?><a href="?page=<?php echo $p; ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>&status=<?php echo e($statusFilter); ?>&q=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>Aucune activité trouvée</h3>
            <p>Il n'y a pas d'activité correspondant à votre recherche.</p>
            <a href="?status=" class="btn btn-primary"><i class="fas fa-eye"></i> Voir toutes les activités</a>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<script>
function toggleReplyForm(commentId) {
    var form = document.getElementById('replyForm-' + commentId);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
        if (form.style.display === 'block') {
            form.querySelector('textarea').focus();
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
