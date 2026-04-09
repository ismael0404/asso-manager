<?php
/**
 * Fonctions d'authentification et utilitaires
 * Fichier: includes/auth.php
 * v3.0 - Ajout: gestion favoris, messages privés, participations workflow,
 *        notifications typées, compteurs avancés
 */

require_once __DIR__ . '/../config/database.php';

/**
 * Vérifie si l'utilisateur est connecté
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Vérifie si l'utilisateur est admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Redirige si l'utilisateur n'est pas connecté
 */
function requireLogin() {
    if (!isLoggedIn()) {
        setFlash('error', 'Vous devez être connecté pour accéder à cette page.');
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Redirige si l'utilisateur n'est pas admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Accès refusé. Vous n\'êtes pas administrateur.');
        header('Location: ' . BASE_URL . '/index.php');
        exit();
    }
}

/**
 * Vérifie si le compte est actif
 */
function checkAccountActive() {
    global $pdo;
    if (!isLoggedIn()) return;
    $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && !$user['is_active']) {
        session_destroy();
        session_start();
        setFlash('error', 'Votre compte a été désactivé. Contactez l\'administrateur.');
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }
}

/**
 * Définir un message flash
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Afficher et supprimer le message flash
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $typeClass = $flash['type'] === 'success' ? 'flash-success' : 'flash-error';
        echo '<div class="flash-message ' . $typeClass . '" id="flashMessage">';
        echo '<span>' . htmlspecialchars($flash['message']) . '</span>';
        echo '<button onclick="this.parentElement.remove()" class="flash-close">&times;</button>';
        echo '</div>';
    }
}

/**
 * Obtenir les infos de l'utilisateur connecté
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Protéger contre XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Upload d'image
 */
function uploadImage($file, $directory = 'uploads') {
    $uploadDir = ROOT_PATH . '/assets/images/' . $directory . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Erreur lors de l\'upload.'];
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Type de fichier non autorisé. Formats acceptés: JPG, PNG, GIF, WEBP.'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Le fichier est trop volumineux. Taille maximale: 5MB.'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $directory . '/' . $filename];
    }
    
    return ['success' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.'];
}

// =====================================================
// FONCTIONS v2.0
// =====================================================

/**
 * Enregistrer une action dans les logs
 */
function addLog($action, $entityType = null, $entityId = null) {
    global $pdo;
    $userId = $_SESSION['user_id'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, entity_type, entity_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $entityType, $entityId]);
}

/**
 * Ajouter une notification pour un utilisateur
 */
function addNotification($userId, $message, $link = null, $type = 'general') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $message, $link]);
}

/**
 * Notifier tous les utilisateurs (sauf l'admin courant)
 */
function notifyAllUsers($message, $link = null, $type = 'general') {
    global $pdo;
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id != ? AND is_active = 1");
    $stmt->execute([$currentUserId]);
    $users = $stmt->fetchAll();
    
    $insertStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
    foreach ($users as $user) {
        $insertStmt->execute([$user['id'], $type, $message, $link]);
    }
}

/**
 * Compter les notifications non lues de l'utilisateur connecté
 */
function getUnreadNotifCount() {
    global $pdo;
    if (!isLoggedIn()) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch()['c'];
}

/**
 * Compter les messages non lus (contacts) - pour admin
 */
function getUnreadMsgCount() {
    global $pdo;
    if (!isAdmin()) return 0;
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM contacts WHERE is_read = 0");
    return $stmt->fetch()['c'];
}

/**
 * Compter les messages privés non lus
 */
function getUnreadPrivateMsgCount() {
    global $pdo;
    if (!isLoggedIn()) return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch()['c'];
}

/**
 * Compter les demandes de participation en attente (admin)
 */
function getPendingParticipationsCount() {
    global $pdo;
    if (!isAdmin()) return 0;
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM participations WHERE status = 'pending'");
    return $stmt->fetch()['c'];
}

/**
 * Formater une date relative (il y a X minutes/heures/jours)
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    
    if ($diff->y > 0) return 'il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return 'il y a ' . $diff->m . ' mois';
    if ($diff->d > 0) return 'il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return 'il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return 'il y a ' . $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
    return 'à l\'instant';
}

// =====================================================
// FONCTIONS v3.0
// =====================================================

/**
 * Vérifier si une activité est en favori
 */
function isFavorite($activityId) {
    global $pdo;
    if (!isLoggedIn()) return false;
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$_SESSION['user_id'], $activityId]);
    return (bool)$stmt->fetch();
}

/**
 * Obtenir le statut de participation d'un utilisateur pour une activité
 */
function getParticipationStatus($activityId, $userId = null) {
    global $pdo;
    $uid = $userId ?? ($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT status FROM participations WHERE user_id = ? AND activity_id = ?");
    $stmt->execute([$uid, $activityId]);
    $result = $stmt->fetch();
    return $result ? $result['status'] : null;
}

/**
 * Compter les participants acceptés pour une activité
 */
function getAcceptedParticipantCount($activityId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE activity_id = ? AND status = 'accepted'");
    $stmt->execute([$activityId]);
    return $stmt->fetch()['c'];
}

/**
 * Vérifier si une activité accepte les inscriptions
 */
function canRegister($activity) {
    if ($activity['registration_status'] === 'closed') return false;
    if ($activity['status'] === 'completed') return false;
    if ($activity['max_participants']) {
        $count = getAcceptedParticipantCount($activity['id']);
        if ($count >= $activity['max_participants']) return false;
    }
    return true;
}

/**
 * Icône de notification selon le type
 */
function getNotifIcon($type) {
    $icons = [
        'activity' => 'fa-calendar-plus',
        'participation' => 'fa-hand-paper',
        'comment' => 'fa-comment',
        'message' => 'fa-envelope',
        'favorite' => 'fa-heart',
        'general' => 'fa-bell'
    ];
    return $icons[$type] ?? 'fa-bell';
}
