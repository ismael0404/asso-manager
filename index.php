<?php
/**
 * Page d'accueil v2.0
 * Fichier: index.php
 * Modification: afficher uniquement les contenus publiés
 */
$pageTitle = 'Accueil';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Récupérer les statistiques (publiés uniquement)
$stmtActivities = $pdo->query("SELECT COUNT(*) as total FROM activities WHERE publication_status='published'");
$totalActivities = $stmtActivities->fetch()['total'];

$stmtMembers = $pdo->query("SELECT COUNT(*) as total FROM members WHERE status='active'");
$totalMembers = $stmtMembers->fetch()['total'];

$stmtPosts = $pdo->query("SELECT COUNT(*) as total FROM posts WHERE publication_status='published'");
$totalPosts = $stmtPosts->fetch()['total'];

// Activités récentes (publiées uniquement)
$stmtRecentActivities = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM participations WHERE activity_id=a.id) as participant_count FROM activities a WHERE a.publication_status='published' ORDER BY a.activity_date DESC LIMIT 3");
$recentActivities = $stmtRecentActivities->fetchAll();

// Articles récents (publiés uniquement)
$stmtRecentPosts = $pdo->query("SELECT p.*, u.full_name as author_name FROM posts p LEFT JOIN users u ON p.author_id = u.id WHERE p.publication_status = 'published' ORDER BY p.created_at DESC LIMIT 3");
$recentPosts = $stmtRecentPosts->fetchAll();

// Traitement formulaire de contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        $stmt = $pdo->prepare("INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message]);
        
        // Notifier les admins
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $admin) {
            addNotification($admin['id'], 'Nouveau message de ' . $name . ' : ' . $subject, '/admin/messages.php');
        }
        
        setFlash('success', 'Votre message a été envoyé avec succès !');
        header('Location: ' . BASE_URL . '/index.php#contact');
        exit();
    } else {
        setFlash('error', 'Veuillez remplir tous les champs.');
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-blob hero-blob-1"></div>
    <div class="hero-blob hero-blob-2"></div>
    <div class="hero-content">
        <div class="hero-badge">
            <i class="fas fa-star"></i> Plateforme de gestion associative
        </div>
        <h1>Ensemble, construisons un <span>avenir meilleur</span></h1>
        <p>Gérez vos activités, membres et publications en toute simplicité avec notre plateforme moderne et intuitive.</p>
        <div class="hero-buttons">
            <?php if (!isLoggedIn()): ?>
                <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-lg btn-primary">
                    <i class="fas fa-rocket"></i> Rejoindre l'association
                </a>
                <a href="#activities" class="btn btn-lg btn-outline">
                    <i class="fas fa-eye"></i> Découvrir nos activités
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>/user/activities.php" class="btn btn-lg btn-primary">
                    <i class="fas fa-calendar-alt"></i> Voir les activités
                </a>
            <?php endif; ?>
        </div>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-number"><?php echo $totalActivities; ?></span>
                <span class="stat-label">Activités</span>
            </div>
            <div class="hero-stat">
                <span class="stat-number"><?php echo $totalMembers; ?></span>
                <span class="stat-label">Membres</span>
            </div>
            <div class="hero-stat">
                <span class="stat-number"><?php echo $totalPosts; ?></span>
                <span class="stat-label">Publications</span>
            </div>
        </div>
    </div>
</section>

<!-- Activités Section -->
<section class="section" id="activities">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fas fa-calendar"></i> Activités</span>
            <h2>Nos Activités Récentes</h2>
            <p>Découvrez les dernières activités organisées par notre association</p>
        </div>
        
        <?php if (count($recentActivities) > 0): ?>
        <div class="grid-3">
            <?php foreach ($recentActivities as $activity): ?>
            <div class="card card-clickable" onclick="window.location='<?php echo BASE_URL; ?>/user/activities.php?detail=<?php echo $activity['id']; ?>'">
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
                    </div>
                    <h3 class="card-title"><?php echo e($activity['title']); ?></h3>
                    <p class="card-text"><?php echo e(mb_strimwidth($activity['description'], 0, 120, '...')); ?></p>
                    <div class="card-meta">
                        <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($activity['activity_date'])); ?></span>
                        <?php if ($activity['location']): ?>
                        <span><i class="fas fa-map-marker-alt"></i> <?php echo e(mb_strimwidth($activity['location'], 0, 25, '...')); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="<?php echo BASE_URL; ?>/user/activities.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Voir toutes les activités</a>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>Aucune activité pour le moment</h3>
            <p>Revenez bientôt pour découvrir nos prochaines activités !</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Publications Section -->
<section class="section" id="posts" style="background: var(--white);">
    <div class="container">
        <div class="section-header">
            <span class="section-badge"><i class="fas fa-newspaper"></i> Actualités</span>
            <h2>Dernières Publications</h2>
            <p>Restez informé des dernières nouvelles de notre association</p>
        </div>
        
        <?php if (count($recentPosts) > 0): ?>
        <div class="grid-3">
            <?php foreach ($recentPosts as $post): ?>
            <div class="card">
                <div class="card-img">
                    <?php if ($post['image']): ?>
                        <img src="<?php echo BASE_URL . '/assets/images/' . e($post['image']); ?>" alt="<?php echo e($post['title']); ?>">
                    <?php else: ?>
                        <i class="fas fa-newspaper"></i>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <span class="badge badge-upcoming mb-1"><?php echo e(ucfirst($post['category'])); ?></span>
                    <h3 class="card-title"><?php echo e($post['title']); ?></h3>
                    <p class="card-text"><?php echo e(mb_strimwidth($post['content'], 0, 120, '...')); ?></p>
                    <div class="card-meta">
                        <span><i class="fas fa-user"></i> <?php echo e($post['author_name']); ?></span>
                        <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($post['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-newspaper"></i>
            <h3>Aucune publication pour le moment</h3>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Contact Section -->
<section class="section contact-section" id="contact">
    <div class="container">
        <div class="section-header" style="padding-top: 40px;">
            <span class="section-badge" style="background:rgba(255,255,255,0.1); color:var(--primary-light);">
                <i class="fas fa-envelope"></i> Contact
            </span>
            <h2>Contactez-nous</h2>
            <p>Une question ? N'hésitez pas à nous écrire</p>
        </div>
        
        <div class="contact-grid">
            <div class="contact-info">
                <h3>Nos Coordonnées</h3>
                <div class="contact-info-item">
                    <div class="icon-box"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <p>Adresse</p>
                        <p>Abidjan, Côte d'Ivoire</p>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="icon-box"><i class="fas fa-phone"></i></div>
                    <div>
                        <p>Téléphone</p>
                        <p>+225 07 00 00 00</p>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="icon-box"><i class="fas fa-envelope"></i></div>
                    <div>
                        <p>Email</p>
                        <p>contact@association.com</p>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="icon-box"><i class="fas fa-clock"></i></div>
                    <div>
                        <p>Horaires</p>
                        <p>Lun - Ven : 8h - 17h</p>
                    </div>
                </div>
            </div>
            
            <div class="contact-form-card">
                <form method="POST" action="<?php echo BASE_URL; ?>/index.php#contact">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom complet</label>
                            <input type="text" name="name" class="form-control" placeholder="Votre nom" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Votre email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Sujet</label>
                        <input type="text" name="subject" class="form-control" placeholder="Sujet du message" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" placeholder="Votre message..." required></textarea>
                    </div>
                    <button type="submit" name="contact_submit" class="btn btn-primary btn-lg w-full">
                        <i class="fas fa-paper-plane"></i> Envoyer le message
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
