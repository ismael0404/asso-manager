<?php
/**
 * Gestion des publications personnelles (Utilisateur)
 * Fichier: user/my-posts.php
 */
$pageTitle = 'Mes Actualités';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Rediriger si non connecté
if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = $_POST['category'] ?? 'general';
        $publicationStatus = $_POST['publication_status'] ?? 'published';
        $isPublished = ($publicationStatus === 'published') ? 1 : 0;
        $imagePath = null;
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['image'], 'posts');
            if ($upload['success']) $imagePath = $upload['filename'];
        }
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content, image, category, author_id, is_published, publication_status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $content, $imagePath, $category, $_SESSION['user_id'], $isPublished, $publicationStatus]);
            $newId = $pdo->lastInsertId();
            addLog('a publié une nouvelle actualité : "' . $title . '"', 'post', $newId);
            setFlash('success', 'Actualité ajoutée avec succès !');
        } else {
            $id = (int)$_POST['id'];
            
            // Vérifier que c'est bien son post
            $stmtCheck = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
            $stmtCheck->execute([$id]);
            $postOwner = $stmtCheck->fetchColumn();
            
            if ($postOwner == $_SESSION['user_id']) {
                if ($imagePath) {
                    $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, image=?, category=?, is_published=?, publication_status=? WHERE id=?");
                    $stmt->execute([$title, $content, $imagePath, $category, $isPublished, $publicationStatus, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, category=?, is_published=?, publication_status=? WHERE id=?");
                    $stmt->execute([$title, $content, $category, $isPublished, $publicationStatus, $id]);
                }
                addLog('a modifié son actualité "' . $title . '"', 'post', $id);
                setFlash('success', 'Actualité modifiée !');
            } else {
                setFlash('error', 'Vous n\'êtes pas autorisé à modifier cette actualité.');
            }
        }
        header('Location: ' . BASE_URL . '/user/my-posts.php');
        exit();
    }
    
    // Toggle publication
    if ($action === 'toggle_publish') {
        $id = (int)$_POST['id'];
        
        $stmtCheck = $pdo->prepare("SELECT author_id FROM posts WHERE id = ?");
        $stmtCheck->execute([$id]);
        $postOwner = $stmtCheck->fetchColumn();
        
        if ($postOwner == $_SESSION['user_id']) {
            $newStatus = $_POST['new_status'] ?? 'published';
            $isPublished = ($newStatus === 'published') ? 1 : 0;
            $pdo->prepare("UPDATE posts SET publication_status=?, is_published=? WHERE id=?")->execute([$newStatus, $isPublished, $id]);
            $label = $newStatus === 'published' ? 'publiée' : 'mise en brouillon';
            addLog('a ' . $label . ' son actualité #' . $id, 'post', $id);
            setFlash('success', 'Actualité ' . $label . ' !');
        } else {
            setFlash('error', 'Action non autorisée.');
        }
        header('Location: ' . BASE_URL . '/user/my-posts.php');
        exit();
    }
}

// Suppression
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    $stmtCheck = $pdo->prepare("SELECT author_id, title FROM posts WHERE id = ?");
    $stmtCheck->execute([$id]);
    $postData = $stmtCheck->fetch();
    
    if ($postData && $postData['author_id'] == $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$id]);
        addLog('a supprimé son actualité "' . $postData['title'] . '"', 'post', $id);
        setFlash('success', 'Actualité supprimée.');
    } else {
        setFlash('error', 'Action non autorisée.');
    }
    header('Location: ' . BASE_URL . '/user/my-posts.php');
    exit();
}

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->prepare("SELECT COUNT(*) as c FROM posts WHERE author_id = ?");
$total->execute([$_SESSION['user_id']]);
$totalCount = $total->fetch()['c'];
$totalPages = ceil($totalCount / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT p.*, (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count 
    FROM posts p 
    WHERE p.author_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$_SESSION['user_id']]);
$posts = $stmt->fetchAll();

$editPost = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND author_id = ?");
    $stmt->execute([(int)$_GET['edit'], $_SESSION['user_id']]);
    $editPost = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container" style="padding: 40px 0;">
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-newspaper"></i> Mes Actualités</h1>
            <p>Gérez vos publications, articles et annonces</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('postModal')">
            <i class="fas fa-plus"></i> Nouvelle Actualité
        </button>
    </div>
    
    <div class="table-card mt-4">
        <div class="table-header">
            <h3><?php echo $totalCount; ?> publication(s)</h3>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Publication</th>
                    <th>Commentaires</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><strong class="truncate"><a href="<?php echo BASE_URL; ?>/user/posts.php?detail=<?php echo $post['id']; ?>" style="color: inherit;"><?php echo e($post['title']); ?></a></strong></td>
                    <td><span class="badge badge-upcoming"><?php echo e(translateStatus($post['category'])); ?></span></td>
                    <td>
                        <?php if (($post['publication_status'] ?? 'published') === 'published'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="new_status" value="draft">
                                <button type="submit" class="badge badge-active btn-badge" title="Cliquer pour mettre en brouillon"><i class="fas fa-eye"></i> Publié</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                <input type="hidden" name="new_status" value="published">
                                <button type="submit" class="badge badge-inactive btn-badge" title="Cliquer pour publier"><i class="fas fa-eye-slash"></i> Brouillon</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $post['comment_count']; ?></td>
                    <td><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></td>
                    <td>
                        <div class="table-actions">
                            <a href="?edit=<?php echo $post['id']; ?>" class="btn btn-icon btn-warning"><i class="fas fa-edit"></i></a>
                            <a href="?delete=<?php echo $post['id']; ?>" class="btn btn-icon btn-danger" data-confirm="Supprimer cette actualité ?"><i class="fas fa-trash"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($posts)): ?>
                <tr><td colspan="6" class="text-center" style="padding:30px;color:var(--text-muted);">Vous n'avez publié aucune actualité pour le moment.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                <?php else: ?><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay <?php echo $editPost ? 'show' : ''; ?>" id="postModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editPost ? 'Modifier l\'Actualité' : 'Nouvelle Actualité'; ?></h3>
            <button class="modal-close" onclick="closeModal('postModal'); <?php if($editPost) echo 'window.location.href=\'my-posts.php\''; ?>">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?php echo $editPost ? 'edit' : 'add'; ?>">
                <?php if ($editPost): ?><input type="hidden" name="id" value="<?php echo $editPost['id']; ?>"><?php endif; ?>
                
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo e($editPost['title'] ?? ''); ?>" placeholder="Titre de votre actualité">
                </div>
                <div class="form-group">
                    <label>Contenu *</label>
                    <textarea name="content" class="form-control" required style="min-height:160px;" placeholder="Détaillez votre actualité ici..."><?php echo e($editPost['content'] ?? ''); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="category" class="form-control">
                            <option value="general" <?php echo ($editPost['category'] ?? '') === 'general' ? 'selected' : ''; ?>>Général</option>
                            <option value="actualite" <?php echo ($editPost['category'] ?? '') === 'actualite' ? 'selected' : ''; ?>>Actualité</option>
                            <option value="annonce" <?php echo ($editPost['category'] ?? '') === 'annonce' ? 'selected' : ''; ?>>Annonce</option>
                            <option value="evenement" <?php echo ($editPost['category'] ?? '') === 'evenement' ? 'selected' : ''; ?>>Événement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Statut initial</label>
                        <select name="publication_status" class="form-control">
                            <option value="published" <?php echo ($editPost['publication_status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Publier immédiatement</option>
                            <option value="draft" <?php echo ($editPost['publication_status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Enregistrer comme brouillon</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Image d'illustration (optionnel)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('postModal'); <?php if($editPost) echo 'window.location.href=\'my-posts.php\''; ?>">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editPost ? 'Enregistrer les modifications' : 'Créer l\'actualité'; ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
