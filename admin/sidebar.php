<?php
/**
 * Sidebar Admin v3.0
 * Fichier: admin/sidebar.php
 * Ajouts: Utilisateurs, participations en attente, messages privés
 */
$currentAdminPage = basename($_SERVER['PHP_SELF'], '.php');
$sidebarUnreadMsgs = getUnreadMsgCount();
$sidebarPendingParts = getPendingParticipationsCount();
$sidebarUnreadPrivate = getUnreadPrivateMsgCount();
?>
<aside class="admin-sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-shield-alt"></i> Administration</h3>
    </div>
    <nav class="sidebar-menu">
        <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="sidebar-link <?php echo $currentAdminPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/activities.php" class="sidebar-link <?php echo $currentAdminPage === 'activities' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Activités
            <?php if ($sidebarPendingParts > 0): ?>
                <span class="sidebar-badge"><?php echo $sidebarPendingParts; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/posts.php" class="sidebar-link <?php echo $currentAdminPage === 'posts' ? 'active' : ''; ?>">
            <i class="fas fa-newspaper"></i> Publications
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/members.php" class="sidebar-link <?php echo $currentAdminPage === 'members' ? 'active' : ''; ?>">
            <i class="fas fa-id-card"></i> Membres
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/users.php" class="sidebar-link <?php echo $currentAdminPage === 'users' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i> Utilisateurs
        </a>
        <div class="sidebar-divider"></div>
        <a href="<?php echo BASE_URL; ?>/admin/messages.php" class="sidebar-link <?php echo $currentAdminPage === 'messages' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> Messages Contact
            <?php if ($sidebarUnreadMsgs > 0): ?>
                <span class="sidebar-badge"><?php echo $sidebarUnreadMsgs; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/user/messages.php" class="sidebar-link">
            <i class="fas fa-comment-dots"></i> Messages Privés
            <?php if ($sidebarUnreadPrivate > 0): ?>
                <span class="sidebar-badge"><?php echo $sidebarUnreadPrivate; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/logs.php" class="sidebar-link <?php echo $currentAdminPage === 'logs' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Historique
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/settings.php" class="sidebar-link <?php echo $currentAdminPage === 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Paramètres
        </a>
        <div class="sidebar-divider"></div>
        <a href="<?php echo BASE_URL; ?>/index.php" class="sidebar-link">
            <i class="fas fa-home"></i> Voir le site
        </a>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="sidebar-link">
            <i class="fas fa-sign-out-alt"></i> Déconnexion
        </a>
    </nav>
</aside>
