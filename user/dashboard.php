<?php
/**
 * Tableau de bord utilisateur
 * Fichier: user/dashboard.php
 * Affiche: stats personnelles, activités récentes, notifications, profil
 */
$pageTitle = 'Mon Dashboard';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = getCurrentUser();
$userId = $_SESSION['user_id'];

// Statistiques personnelles
$stats = [];

// Nombre total d'activités rejointes (acceptées)
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([$userId]);
$stats['joined'] = $stmt->fetch()['c'];

// Demandes en attente
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$stats['pending'] = $stmt->fetch()['c'];

// Favoris
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM favorites WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['favorites'] = $stmt->fetch()['c'];

// Commentaires
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM comments WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['comments'] = $stmt->fetch()['c'];

// Messages non lus
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$stats['unread_messages'] = $stmt->fetch()['c'];

// Taux de participation
$totalActivities = $pdo->query("SELECT COUNT(*) as c FROM activities WHERE publication_status = 'published'")->fetch()['c'];
$stats['participation_rate'] = $totalActivities > 0 ? round(($stats['joined'] / $totalActivities) * 100) : 0;

// Dernière activité
$stmt = $pdo->prepare("
    SELECT a.title, a.activity_date, p.status 
    FROM participations p 
    JOIN activities a ON p.activity_id = a.id 
    WHERE p.user_id = ? AND p.status = 'accepted'
    ORDER BY a.activity_date DESC LIMIT 1
");
$stmt->execute([$userId]);
$lastActivity = $stmt->fetch();

// Activités récentes (participations)
$stmt = $pdo->prepare("
    SELECT a.*, p.status as participation_status, p.created_at as joined_at
    FROM participations p 
    JOIN activities a ON p.activity_id = a.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$recentActivities = $stmt->fetchAll();

// Notifications récentes
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentNotifs = $stmt->fetchAll();

// Activités à venir (où l'utilisateur participe)
$stmt = $pdo->prepare("
    SELECT a.* FROM participations p 
    JOIN activities a ON p.activity_id = a.id 
    WHERE p.user_id = ? AND p.status = 'accepted' AND a.activity_date >= CURDATE()
    ORDER BY a.activity_date ASC LIMIT 3
");
$stmt->execute([$userId]);
$upcomingActivities = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Dashboard Header -->
<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-tachometer-alt"></i> Mon <span>Dashboard</span></h1>
        <p>Bienvenue <?php echo e($user['full_name']); ?> ! Voici un aperçu de votre activité.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <!-- Profil rapide -->
        <div class="user-dashboard-profile mb-3">
            <div class="udp-avatar">
                <?php if ($user['avatar'] && $user['avatar'] !== 'default.png'): ?>
                    <img src="<?php echo BASE_URL . '/assets/images/' . e($user['avatar']); ?>" alt="Avatar">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <div class="udp-info">
                <h2><?php echo e($user['full_name']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo e($user['email']); ?></p>
                <?php if ($user['bio']): ?>
                    <p class="udp-bio"><?php echo e(mb_strimwidth($user['bio'], 0, 200, '...')); ?></p>
                <?php endif; ?>
                <div class="udp-actions">
                    <a href="<?php echo BASE_URL; ?>/user/profile.php" class="btn btn-sm btn-secondary"><i class="fas fa-user-edit"></i> Modifier profil</a>
                    <a href="<?php echo BASE_URL; ?>/user/my-activities.php" class="btn btn-sm btn-primary"><i class="fas fa-calendar-check"></i> Mes Activités</a>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid mb-3">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['joined']; ?></h3>
                    <p>Activités rejointes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending']; ?></h3>
                    <p>En attente</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#fce4ec;color:#e91e63;"><i class="fas fa-heart"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['favorites']; ?></h3>
                    <p>Favoris</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-chart-pie"></i></div>
                <div class="stat-info">
                    <h3><?php echo $stats['participation_rate']; ?>%</h3>
                    <p>Taux de participation</p>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <!-- Activités récentes -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-history"></i> Mes Participations Récentes</h3>
                    <a href="<?php echo BASE_URL; ?>/user/my-activities.php" class="btn btn-sm btn-secondary">Tout voir</a>
                </div>
                <div class="dashboard-list">
                    <?php foreach ($recentActivities as $act): ?>
                    <div class="dashboard-list-item">
                        <div class="dli-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="dli-content">
                            <a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $act['id']; ?>" class="dli-title"><?php echo e($act['title']); ?></a>
                            <span class="dli-date"><?php echo date('d/m/Y', strtotime($act['activity_date'])); ?></span>
                        </div>
                        <div class="dli-status">
                            <?php if ($act['participation_status'] === 'accepted'): ?>
                                <span class="badge badge-completed"><i class="fas fa-check"></i> Accepté</span>
                            <?php elseif ($act['participation_status'] === 'pending'): ?>
                                <span class="badge badge-ongoing"><i class="fas fa-hourglass-half"></i> En attente</span>
                            <?php else: ?>
                                <span class="badge badge-inactive"><i class="fas fa-times"></i> Refusé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentActivities)): ?>
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-calendar-times" style="font-size:2rem;"></i>
                        <p>Aucune participation. <a href="<?php echo BASE_URL; ?>/user/activities.php">Découvrir les activités</a></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Notifications récentes -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-bell"></i> Notifications Récentes</h3>
                    <a href="<?php echo BASE_URL; ?>/user/notifications.php" class="btn btn-sm btn-secondary">Tout voir</a>
                </div>
                <div class="dashboard-list">
                    <?php foreach ($recentNotifs as $notif): ?>
                    <div class="dashboard-list-item <?php echo !$notif['is_read'] ? 'dli-unread' : ''; ?>">
                        <div class="dli-icon dli-icon-notif">
                            <i class="fas <?php echo getNotifIcon($notif['type'] ?? 'general'); ?>"></i>
                        </div>
                        <div class="dli-content">
                            <p class="dli-message"><?php echo e($notif['message']); ?></p>
                            <span class="dli-date"><?php echo timeAgo($notif['created_at']); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($recentNotifs)): ?>
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-bell-slash" style="font-size:2rem;"></i>
                        <p>Aucune notification</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Prochaines activités -->
        <?php if (!empty($upcomingActivities)): ?>
        <div class="table-card mt-3">
            <div class="table-header">
                <h3><i class="fas fa-calendar-day"></i> Mes Prochaines Activités</h3>
                <a href="<?php echo BASE_URL; ?>/user/calendar.php" class="btn btn-sm btn-secondary"><i class="fas fa-calendar"></i> Calendrier</a>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Activité</th>
                        <th>Date</th>
                        <th>Lieu</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingActivities as $ua): ?>
                    <tr>
                        <td><strong><?php echo e($ua['title']); ?></strong></td>
                        <td><?php echo date('d/m/Y', strtotime($ua['activity_date'])); ?></td>
                        <td><?php echo e($ua['location'] ?? '-'); ?></td>
                        <td><a href="<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $ua['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
