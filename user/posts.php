<?php
/**
 * Page des Actualités (Publique / Visiteurs et Utilisateurs)
 * Fichier: user/posts.php
 */
$pageTitle = 'Actualités';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Pagination et recherche
$search = $_GET['q'] ?? '';
$categoryFilter = $_GET['category'] ?? '';

// ==========================================
// VUE DÉTAILLÉE D'UNE ACTUALITÉ
// ==========================================
$detailPost = null;
if (isset($_GET['detail'])) {
    $postId = (int)$_GET['detail'];
    
    // Récupérer l'actualité
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as author_name, u.avatar as author_avatar
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE p.id = ? AND p.publication_status = 'published'
    ");
    $stmt->execute([$postId]);
    $detailPost = $stmt->fetch();
    
    if (!$detailPost) {
        setFlash('error', 'Actualité introuvable.');
        header('Location: ' . BASE_URL . '/user/posts.php');
        exit();
    }
    
    // Gérer l'ajout de commentaire (uniquement pour connectés)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment']) && isLoggedIn()) {
        $content = trim($_POST['comment_content'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        
        if (!empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO comments (user_id, post_id, parent_id, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $postId, $parentId, $content]);
            
            // Notifier l'auteur de l'actualité si ce n'est pas lui-même
            if ($detailPost['author_id'] != $_SESSION['user_id']) {
                addNotification($detailPost['author_id'], $_SESSION['user_name'] . ' a commenté votre actualité.', BASE_URL . '/user/posts.php?detail=' . $postId, 'comment');
            }
            
            setFlash('success', 'Commentaire ajouté !');
            header('Location: ' . BASE_URL . '/user/posts.php?detail=' . $postId);
            exit();
        }
    }
    
    // Gérer la suppression de commentaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment']) && isLoggedIn()) {
        $commentId = (int)$_POST['comment_id'];
        
        // Vérifier si l'utilisateur est le propriétaire du commentaire ou admin
        $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
        
        if ($comment && ($comment['user_id'] == $_SESSION['user_id'] || isAdmin())) {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
            setFlash('success', 'Commentaire supprimé.');
        } else {
            setFlash('error', 'Action non autorisée.');
        }
        header('Location: ' . BASE_URL . '/user/posts.php?detail=' . $postId);
        exit();
    }
    
    // Récupérer les commentaires
    $stmtComments = $pdo->prepare("
        SELECT c.*, u.full_name, u.avatar 
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.post_id = ? AND c.parent_id IS NULL
        ORDER BY c.created_at DESC
    ");
    $stmtComments->execute([$postId]);
    $comments = $stmtComments->fetchAll();
}

// ==========================================
// VUE LISTE DES ACTUALITÉS
// ==========================================
else {
    $whereClauses = ["p.publication_status = 'published'"];
    $params = [];
    
    if ($search) {
        $whereClauses[] = "(p.title LIKE ? OR p.content LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($categoryFilter) {
        $whereClauses[] = "p.category = ?";
        $params[] = $categoryFilter;
    }
    
    $whereSql = implode(' AND ', $whereClauses);
    
    // Pagination
    $perPage = 9;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $total = $pdo->prepare("SELECT COUNT(*) as c FROM posts p WHERE $whereSql");
    $total->execute($params);
    $totalCount = $total->fetch()['c'];
    $totalPages = ceil($totalCount / $perPage);
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as author_name,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count
        FROM posts p
        LEFT JOIN users u ON p.author_id = u.id
        WHERE $whereSql
        ORDER BY p.created_at DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute($params);
    $postsList = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($detailPost): ?>
    <!-- Vue détaillée -->
    <section class="section">
        <div class="container" style="max-width:900px;">
            <a href="<?php echo BASE_URL; ?>/user/posts.php" class="btn btn-secondary btn-sm mb-3"><i class="fas fa-arrow-left"></i> Retour aux actualités</a>
            
            <div class="activity-detail-card" style="background: var(--white); border-radius: var(--radius-xl); box-shadow: var(--shadow-lg); overflow: hidden;">
                <?php if ($detailPost['image']): ?>
                    <div style="width: 100%; max-height: 400px; overflow: hidden;">
                        <img src="<?php echo BASE_URL . '/assets/images/' . e($detailPost['image']); ?>" alt="<?php echo e($detailPost['title']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    </div>
                <?php endif; ?>
                
                <div style="padding: 40px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                        <div>
                            <span class="badge badge-upcoming mb-2"><?php echo e(translateStatus($detailPost['category'])); ?></span>
                            <h1 style="font-size: 2.2rem; color: var(--gray-900); margin-bottom: 8px;"><?php echo e($detailPost['title']); ?></h1>
                            <div style="color: var(--text-muted); display: flex; align-items: center; gap: 16px;">
                                <span><i class="fas fa-user"></i> Publié par <?php echo e($detailPost['author_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($detailPost['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="activity-content" style="font-size: 1.1rem; line-height: 1.8; color: var(--text-color); margin-bottom: 40px;">
                        <?php echo nl2br(e($detailPost['content'])); ?>
                    </div>
                    
                    <hr style="border: 0; border-top: 1px solid var(--border-color); margin-bottom: 30px;">
                    
                    <!-- Section Commentaires -->
                    <div class="comments-section" id="comments">
                        <h3 class="mb-3"><i class="fas fa-comments"></i> Commentaires (<?php echo count($comments); ?>)</h3>
                        
                        <?php if (isLoggedIn()): ?>
                            <form method="POST" class="comment-form mb-4">
                                <div class="form-group">
                                    <textarea name="comment_content" class="form-control" rows="3" placeholder="Ajouter un commentaire..." required></textarea>
                                </div>
                                <button type="submit" name="submit_comment" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle"></i> <a href="<?php echo BASE_URL; ?>/login.php" style="color: var(--primary-dark); font-weight: bold;">Connectez-vous</a> pour laisser un commentaire.
                            </div>
                        <?php endif; ?>
                        
                        <div class="comments-list">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment-item" id="comment-<?php echo $comment['id']; ?>">
                                    <div class="comment-avatar">
                                        <?php if ($comment['avatar'] && $comment['avatar'] !== 'default.png'): ?>
                                            <img src="<?php echo BASE_URL . '/assets/images/' . e($comment['avatar']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <div class="avatar-placeholder"><i class="fas fa-user"></i></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="comment-content">
                                        <div class="comment-header">
                                            <strong><?php echo e($comment['full_name']); ?></strong>
                                            <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></span>
                                        </div>
                                        <div class="comment-text">
                                            <?php echo nl2br(e($comment['content'])); ?>
                                        </div>
                                        <div class="comment-actions">
                                            <?php if (isLoggedIn()): ?>
                                                <button class="btn btn-sm btn-link" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">Répondre</button>
                                                <?php if ($comment['user_id'] == $_SESSION['user_id'] || isAdmin()): ?>
                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce commentaire ?');">
                                                        <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                                        <button type="submit" name="delete_comment" class="btn btn-sm btn-link text-danger">Supprimer</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Formulaire de réponse (caché) -->
                                        <form method="POST" class="reply-form mt-2" id="reply-form-<?php echo $comment['id']; ?>" style="display:none;">
                                            <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                            <div class="form-group mb-2">
                                                <textarea name="comment_content" class="form-control" rows="2" placeholder="Votre réponse..." required></textarea>
                                            </div>
                                            <button type="submit" name="submit_comment" class="btn btn-primary btn-sm">Répondre</button>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">Annuler</button>
                                        </form>
                                        
                                        <!-- Réponses nested -->
                                        <?php
                                        $stmtReplies = $pdo->prepare("
                                            SELECT c.*, u.full_name, u.avatar 
                                            FROM comments c
                                            JOIN users u ON c.user_id = u.id
                                            WHERE c.parent_id = ?
                                            ORDER BY c.created_at ASC
                                        ");
                                        $stmtReplies->execute([$comment['id']]);
                                        $replies = $stmtReplies->fetchAll();
                                        ?>
                                        <?php if (count($replies) > 0): ?>
                                            <div class="comment-replies mt-3 pl-4" style="border-left: 2px solid var(--gray-200);">
                                                <?php foreach ($replies as $reply): ?>
                                                    <div class="comment-item mb-2">
                                                        <div class="comment-avatar" style="width: 32px; height: 32px;">
                                                            <?php if ($reply['avatar'] && $reply['avatar'] !== 'default.png'): ?>
                                                                <img src="<?php echo BASE_URL . '/assets/images/' . e($reply['avatar']); ?>" alt="Avatar">
                                                            <?php else: ?>
                                                                <div class="avatar-placeholder"><i class="fas fa-user" style="font-size: 0.8rem;"></i></div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="comment-content">
                                                            <div class="comment-header">
                                                                <strong><?php echo e($reply['full_name']); ?></strong>
                                                                <span class="comment-date"><?php echo date('d/m/Y H:i', strtotime($reply['created_at'])); ?></span>
                                                            </div>
                                                            <div class="comment-text">
                                                                <?php echo nl2br(e($reply['content'])); ?>
                                                            </div>
                                                            <?php if (isLoggedIn() && ($reply['user_id'] == $_SESSION['user_id'] || isAdmin())): ?>
                                                                <div class="comment-actions">
                                                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette réponse ?');">
                                                                        <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                                                        <button type="submit" name="delete_comment" class="btn btn-sm btn-link text-danger">Supprimer</button>
                                                                    </form>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($comments)): ?>
                                <p class="text-muted">Aucun commentaire pour le moment. Soyez le premier !</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
    function toggleReplyForm(commentId) {
        const form = document.getElementById('reply-form-' + commentId);
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }
    </script>

<?php else: ?>
    <!-- Liste des actualités -->
    <section class="section">
        <div class="container">
            <div class="section-header text-center mb-5">
                <h1>Toutes nos Actualités</h1>
                <p>Restez informé des dernières nouvelles et publications de notre communauté</p>
            </div>
            
            <!-- Recherche + Filtres -->
            <div class="activity-filters-bar mb-4">
                <form method="GET" class="activity-search-form" style="display: flex; gap: 10px; width: 100%;">
                    <div class="search-box" style="flex:1;">
                        <i class="fas fa-search"></i>
                        <input type="text" name="q" placeholder="Rechercher une actualité..." value="<?php echo e($search); ?>">
                    </div>
                    <select name="category" class="form-control" style="width: auto;">
                        <option value="">Toutes les catégories</option>
                        <option value="general" <?php echo $categoryFilter === 'general' ? 'selected' : ''; ?>>Général</option>
                        <option value="actualite" <?php echo $categoryFilter === 'actualite' ? 'selected' : ''; ?>>Actualité</option>
                        <option value="annonce" <?php echo $categoryFilter === 'annonce' ? 'selected' : ''; ?>>Annonce</option>
                        <option value="evenement" <?php echo $categoryFilter === 'evenement' ? 'selected' : ''; ?>>Événement</option>
                    </select>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filtrer</button>
                </form>
            </div>
            
            <?php if (count($postsList) > 0): ?>
                <div class="grid-3">
                    <?php foreach ($postsList as $post): ?>
                        <div class="card card-clickable" onclick="window.location='?detail=<?php echo $post['id']; ?>'">
                            <div class="card-img" style="height: 200px;">
                                <?php if ($post['image']): ?>
                                    <img src="<?php echo BASE_URL . '/assets/images/' . e($post['image']); ?>" alt="<?php echo e($post['title']); ?>">
                                <?php else: ?>
                                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:var(--gray-100); color:var(--primary); font-size:3rem;">
                                        <i class="fas fa-newspaper"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="mb-2">
                                    <span class="badge badge-upcoming"><?php echo e(translateStatus($post['category'])); ?></span>
                                </div>
                                <h3 class="card-title"><?php echo e($post['title']); ?></h3>
                                <p class="card-text"><?php echo e(mb_strimwidth($post['content'], 0, 100, '...')); ?></p>
                                <div class="card-meta" style="margin-top: 16px;">
                                    <span><i class="fas fa-user"></i> <?php echo e($post['author_name']); ?></span>
                                    <span><i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?></span>
                                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination mt-4">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($categoryFilter); ?>"><i class="fas fa-chevron-left"></i></a>
                    <?php endif; ?>
                    
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p == $page): ?>
                            <span class="active"><?php echo $p; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $p; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($categoryFilter); ?>"><?php echo $p; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search); ?>&category=<?php echo urlencode($categoryFilter); ?>"><i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-newspaper"></i>
                    <h3>Aucune actualité trouvée</h3>
                    <p>Essayez de modifier vos filtres de recherche.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
