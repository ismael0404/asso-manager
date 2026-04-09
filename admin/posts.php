<?php
/**
 * Gestion des publications (Admin) v2.0
 * Fichier: admin/posts.php
 * Ajouts: logs, publication workflow (draft/published)
 */
$pageTitle = 'Gestion des Publications';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

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
            addLog('a ajouté la publication "' . $title . '"', 'post', $newId);
            
            if ($publicationStatus === 'published') {
                notifyAllUsers('Nouvelle publication : ' . $title, '/index.php#posts');
            }
            
            setFlash('success', 'Publication ajoutée !');
        } else {
            $id = (int)$_POST['id'];
            if ($imagePath) {
                $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, image=?, category=?, is_published=?, publication_status=? WHERE id=?");
                $stmt->execute([$title, $content, $imagePath, $category, $isPublished, $publicationStatus, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, category=?, is_published=?, publication_status=? WHERE id=?");
                $stmt->execute([$title, $content, $category, $isPublished, $publicationStatus, $id]);
            }
            addLog('a modifié la publication "' . $title . '"', 'post', $id);
            setFlash('success', 'Publication modifiée !');
        }
        header('Location: ' . BASE_URL . '/admin/posts.php');
        exit();
    }
    
    // Toggle publication
    if ($action === 'toggle_publish') {
        $id = (int)$_POST['id'];
        $newStatus = $_POST['new_status'] ?? 'published';
        $isPublished = ($newStatus === 'published') ? 1 : 0;
        $pdo->prepare("UPDATE posts SET publication_status=?, is_published=? WHERE id=?")->execute([$newStatus, $isPublished, $id]);
        $label = $newStatus === 'published' ? 'publiée' : 'mise en brouillon';
        addLog('a ' . $label . ' la publication #' . $id, 'post', $id);
        setFlash('success', 'Publication ' . $label . ' !');
        header('Location: ' . BASE_URL . '/admin/posts.php');
        exit();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT title FROM posts WHERE id=?");
    $stmt->execute([$id]);
    $postTitle = $stmt->fetchColumn();
    
    $pdo->prepare("DELETE FROM posts WHERE id=?")->execute([$id]);
    addLog('a supprimé la publication "' . $postTitle . '"', 'post', $id);
    setFlash('success', 'Publication supprimée.');
    header('Location: ' . BASE_URL . '/admin/posts.php');
    exit();
}

$perPage = 8;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->query("SELECT COUNT(*) as c FROM posts")->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$posts = $pdo->query("SELECT p.*, u.full_name as author_name FROM posts p LEFT JOIN users u ON p.author_id=u.id ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();

$editPost = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editPost = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-newspaper"></i> Publications</h1>
                <p>Gérez les articles et actualités</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('postModal')">
                <i class="fas fa-plus"></i> Nouvelle Publication
            </button>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> publication(s)</h3>
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
                        <th>Catégorie</th>
                        <th>Auteur</th>
                        <th>Publication</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $i => $post): ?>
                    <tr>
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td><strong class="truncate"><?php echo e($post['title']); ?></strong></td>
                        <td><span class="badge badge-upcoming"><?php echo e(ucfirst($post['category'])); ?></span></td>
                        <td><?php echo e($post['author_name']); ?></td>
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
                        <td><?php echo date('d/m/Y', strtotime($post['created_at'])); ?></td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?php echo $post['id']; ?>" class="btn btn-icon btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?php echo $post['id']; ?>" class="btn btn-icon btn-danger" data-confirm="Supprimer cette publication ?"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($posts)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">Aucune publication</td></tr>
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
</div>

<!-- Modal -->
<div class="modal-overlay <?php echo $editPost ? 'show' : ''; ?>" id="postModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editPost ? 'Modifier la Publication' : 'Nouvelle Publication'; ?></h3>
            <button class="modal-close" onclick="closeModal('postModal'); <?php if($editPost) echo 'window.location.href=\'posts.php\''; ?>">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?php echo $editPost ? 'edit' : 'add'; ?>">
                <?php if ($editPost): ?><input type="hidden" name="id" value="<?php echo $editPost['id']; ?>"><?php endif; ?>
                
                <div class="form-group">
                    <label>Titre *</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo e($editPost['title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Contenu *</label>
                    <textarea name="content" class="form-control" required style="min-height:160px;"><?php echo e($editPost['content'] ?? ''); ?></textarea>
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
                        <label>Publication</label>
                        <select name="publication_status" class="form-control">
                            <option value="published" <?php echo ($editPost['publication_status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>Publié</option>
                            <option value="draft" <?php echo ($editPost['publication_status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Brouillon</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Image (optionnel)</label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('postModal'); <?php if($editPost) echo 'window.location.href=\'posts.php\''; ?>">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editPost ? 'Modifier' : 'Publier'; ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
