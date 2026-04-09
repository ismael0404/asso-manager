<?php
/**
 * Favoris utilisateur
 * Fichier: user/favorites.php
 * Liste des activités mises en favori
 */
$pageTitle = 'Mes Favoris';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Retirer un favori
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_fav'])) {
    $actId = (int)$_POST['remove_fav'];
    $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND activity_id = ?")->execute([$userId, $actId]);
    setFlash('success', 'Activité retirée des favoris.');
    header('Location: ' . BASE_URL . '/user/favorites.php');
    exit();
}

// Pagination
$perPage = 9;
$page = max(1, (int)($_GET['page'] ?? 1));

$totalStmt = $pdo->prepare("SELECT COUNT(*) as c FROM favorites WHERE user_id = ?");
$totalStmt->execute([$userId]);
$total = $totalStmt->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT a.*, f.created_at as fav_date,
    (SELECT COUNT(*) FROM participations WHERE activity_id = a.id AND status = 'accepted') as participant_count
    FROM favorites f 
    JOIN activities a ON f.activity_id = a.id 
    WHERE f.user_id = ? 
    ORDER BY f.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-heart"></i> Mes <span>Favoris</span></h1>
        <p><?php echo $total; ?> activité(s) sauvegardée(s)</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php if (count($favorites) > 0): ?>
        <div class="grid-3">
            <?php foreach ($favorites as $fav): ?>
            <div class="card">
                <div class="card-img">
                    <?php if ($fav['image']): ?>
                        <img src="<?php echo BASE_URL . '/assets/images/' . e($fav['image']); ?>" alt="<?php echo e($fav['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-calendar-check"></i>
                    <?php endif; ?>
                    <div class="card-fav-badge"><i class="fas fa-heart"></i></div>
                </div>
                <div class="card-body">
                    <div class="mb-1" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <span class="badge badge-<?php echo e($fav['status']); ?>"><?php echo e(ucfirst($fav['status'])); ?></span>
                        <span class="participant-count-small"><i class="fas fa-users"></i> <?php echo $fav['participant_count']; ?></span>
                    </div>
                    <h3 class="card-title"><?php echo e($fav['title']); ?></h3>
                    <p class="card-text"><?php echo e(mb_strimwidth($fav['description'], 0, 120, '...')); ?></p>
                    <div class="card-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($fav['activity_date'])); ?></span>
                        <?php if ($fav['location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo e(mb_strimwidth($fav['location'], 0, 25, '...')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $fav['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Détails
                    </a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="remove_fav" value="<?php echo $fav['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="Retirer des favoris">
                            <i class="fas fa-heart-broken"></i>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination mt-4">
            <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                <?php else: ?><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-heart"></i>
            <h3>Aucun favori</h3>
            <p>Vous n'avez pas encore d'activité en favori. Ajoutez des activités en cliquant sur le cœur !</p>
            <a href="<?php echo BASE_URL; ?>/user/activities.php" class="btn btn-primary"><i class="fas fa-calendar-alt"></i> Parcourir les activités</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
