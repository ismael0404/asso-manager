<?php
/**
 * Header template v3.0
 * Fichier: includes/header.php
 * Ajouts: liens dashboard user, favoris, calendrier, mes activités, messages privés
 */
require_once __DIR__ . '/auth.php';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$unreadNotifs = getUnreadNotifCount();
$unreadMsgs = getUnreadMsgCount();
$unreadPrivateMsgs = getUnreadPrivateMsgCount();
$pendingParticipations = getPendingParticipationsCount();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Association Manager - Plateforme de gestion des activités et membres de l'association">
    <title><?php echo isset($pageTitle) ? e($pageTitle) . ' | ' : ''; ?>Association Manager</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <script>
        // Appliquer le dark mode immédiatement pour éviter le flash
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark-mode');
        }
    </script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="<?php echo BASE_URL; ?>/index.php" class="nav-brand">
                <i class="fas fa-hands-helping"></i>
                <span>AssocManager</span>
            </a>
            
            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <ul class="nav-menu" id="navMenu">
                <li><a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Accueil
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/user/activities.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Activités
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php#posts" class="nav-link">
                    <i class="fas fa-newspaper"></i> Actualités
                </a></li>
                <li><a href="<?php echo BASE_URL; ?>/index.php#contact" class="nav-link">
                    <i class="fas fa-envelope"></i> Contact
                </a></li>
                
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/') !== false ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                            <?php if ($pendingParticipations > 0): ?><span class="dropdown-badge"><?php echo $pendingParticipations; ?></span><?php endif; ?>
                        </a></li>
                    <?php else: ?>
                        <li><a href="<?php echo BASE_URL; ?>/user/dashboard.php" class="nav-link <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a></li>
                    <?php endif; ?>

                    <!-- Notifications Bell -->
                    <li class="nav-notif-wrapper">
                        <a href="<?php echo BASE_URL; ?>/user/notifications.php" class="nav-link nav-notif-link" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($unreadNotifs > 0): ?>
                                <span class="notif-badge"><?php echo $unreadNotifs > 9 ? '9+' : $unreadNotifs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Messages privés -->
                    <li class="nav-notif-wrapper">
                        <a href="<?php echo BASE_URL; ?>/user/messages.php" class="nav-link nav-notif-link" title="Messages">
                            <i class="fas fa-comment-dots"></i>
                            <?php if ($unreadPrivateMsgs > 0): ?>
                                <span class="notif-badge"><?php echo $unreadPrivateMsgs > 9 ? '9+' : $unreadPrivateMsgs; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>

                    <!-- Dark Mode Toggle -->
                    <li>
                        <button class="nav-link dark-mode-toggle" id="darkModeToggle" title="Mode sombre" type="button">
                            <i class="fas fa-moon" id="darkModeIcon"></i>
                        </button>
                    </li>

                    <li class="nav-user-menu">
                        <a href="#" class="nav-link nav-user-link">
                            <?php if ($currentUser && $currentUser['avatar'] && $currentUser['avatar'] !== 'default.png'): ?>
                                <img src="<?php echo BASE_URL . '/assets/images/' . e($currentUser['avatar']); ?>" alt="Avatar" class="nav-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <?php echo e($currentUser['full_name'] ?? 'Utilisateur'); ?>
                            <i class="fas fa-chevron-down"></i>
                        </a>
                        <ul class="nav-dropdown">
                            <?php if (!isAdmin()): ?>
                            <li><a href="<?php echo BASE_URL; ?>/user/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                            <?php endif; ?>
                            <li><a href="<?php echo BASE_URL; ?>/user/profile.php"><i class="fas fa-user-cog"></i> Profil</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/user/my-activities.php"><i class="fas fa-calendar-check"></i> Mes Activités</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/user/favorites.php"><i class="fas fa-heart"></i> Favoris</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/user/calendar.php"><i class="fas fa-calendar"></i> Calendrier</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/user/notifications.php"><i class="fas fa-bell"></i> Notifications
                                <?php if ($unreadNotifs > 0): ?><span class="dropdown-badge"><?php echo $unreadNotifs; ?></span><?php endif; ?>
                            </a></li>
                            <li><a href="<?php echo BASE_URL; ?>/user/messages.php"><i class="fas fa-comment-dots"></i> Messages
                                <?php if ($unreadPrivateMsgs > 0): ?><span class="dropdown-badge"><?php echo $unreadPrivateMsgs; ?></span><?php endif; ?>
                            </a></li>
                            <?php if (isAdmin()): ?>
                                <li><a href="<?php echo BASE_URL; ?>/admin/dashboard.php"><i class="fas fa-cogs"></i> Administration</a></li>
                                <li><a href="<?php echo BASE_URL; ?>/admin/messages.php"><i class="fas fa-envelope"></i> Messages Contact
                                    <?php if ($unreadMsgs > 0): ?><span class="dropdown-badge"><?php echo $unreadMsgs; ?></span><?php endif; ?>
                                </a></li>
                            <?php endif; ?>
                            <li class="dropdown-divider"></li>
                            <li><a href="<?php echo BASE_URL; ?>/logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <!-- Dark Mode Toggle (non connecté) -->
                    <li>
                        <button class="nav-link dark-mode-toggle" id="darkModeTogglePublic" title="Mode sombre" type="button">
                            <i class="fas fa-moon"></i>
                        </button>
                    </li>
                    <li><a href="<?php echo BASE_URL; ?>/login.php" class="nav-link <?php echo $currentPage === 'login' ? 'active' : ''; ?>">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>/register.php" class="nav-link btn-nav <?php echo $currentPage === 'register' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i> Inscription
                    </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    
    <!-- Flash Messages -->
    <div class="flash-container">
        <?php getFlash(); ?>
    </div>
    
    <!-- Main Content -->
    <main class="main-content">
