<?php
/**
 * Messagerie privée utilisateur
 * Fichier: user/messages.php
 * Envoyer/recevoir des messages avec l'admin
 */
$pageTitle = 'Messages';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'send') {
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');
        
        if ($receiverId > 0 && !empty($content)) {
            $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, content) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $receiverId, $subject, $content]);
            
            // Notifier le destinataire
            $senderName = $_SESSION['user_name'] ?? 'Un utilisateur';
            addNotification($receiverId, 'Nouveau message de ' . $senderName, '/user/messages.php', 'message');
            
            setFlash('success', 'Message envoyé !');
            header('Location: ' . BASE_URL . '/user/messages.php?conversation=' . $receiverId);
            exit();
        }
    }
}

// Marquer comme lu
if (isset($_GET['markread'])) {
    $msgId = (int)$_GET['markread'];
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?")->execute([$msgId, $userId]);
}

// Récupérer les conversations (groupées par contact)
$conversations = $pdo->prepare("
    SELECT u.id as contact_id, u.full_name, u.avatar, u.role,
        (SELECT content FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message_date,
        (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
    FROM users u
    WHERE u.id IN (
        SELECT DISTINCT sender_id FROM messages WHERE receiver_id = ?
        UNION
        SELECT DISTINCT receiver_id FROM messages WHERE sender_id = ?
    )
    ORDER BY last_message_date DESC
");
$conversations->execute([$userId, $userId, $userId, $userId, $userId, $userId, $userId]);
$conversations = $conversations->fetchAll();

// Vue conversation
$conversationWith = null;
$conversationMessages = [];
if (isset($_GET['conversation'])) {
    $contactId = (int)$_GET['conversation'];
    $contactStmt = $pdo->prepare("SELECT id, full_name, avatar, role FROM users WHERE id = ?");
    $contactStmt->execute([$contactId]);
    $conversationWith = $contactStmt->fetch();
    
    if ($conversationWith) {
        // Marquer les messages reçus comme lus
        $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0")
            ->execute([$contactId, $userId]);
        
        // Récupérer les messages
        $msgStmt = $pdo->prepare("
            SELECT m.*, u.full_name as sender_name, u.avatar as sender_avatar
            FROM messages m 
            JOIN users u ON m.sender_id = u.id
            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $msgStmt->execute([$userId, $contactId, $contactId, $userId]);
        $conversationMessages = $msgStmt->fetchAll();
    }
}

// Nouveau message: obtenir la liste des admins pour les utilisateurs normaux
$adminsList = [];
if (!isAdmin()) {
    $adminsList = $pdo->query("SELECT id, full_name FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
} else {
    // Admin peut écrire à tous les utilisateurs
    $adminsList = $pdo->prepare("SELECT id, full_name FROM users WHERE id != ? AND is_active = 1 ORDER BY full_name");
    $adminsList->execute([$userId]);
    $adminsList = $adminsList->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<section class="hero" style="padding:60px 24px 50px;">
    <div class="hero-content">
        <h1><i class="fas fa-comment-dots"></i> Mes <span>Messages</span></h1>
        <p>Communiquez avec l'administration</p>
    </div>
</section>

<section class="section">
    <div class="container" style="max-width:1000px;">
        <div class="messaging-layout">
            <!-- Liste des conversations -->
            <div class="msg-sidebar">
                <div class="msg-sidebar-header">
                    <h3>Conversations</h3>
                    <button class="btn btn-sm btn-primary" onclick="openModal('newMsgModal')"><i class="fas fa-plus"></i> Nouveau</button>
                </div>
                <div class="msg-conversation-list">
                    <?php foreach ($conversations as $conv): ?>
                    <a href="?conversation=<?php echo $conv['contact_id']; ?>" 
                       class="msg-conv-item <?php echo (isset($_GET['conversation']) && (int)$_GET['conversation'] === $conv['contact_id']) ? 'active' : ''; ?> <?php echo $conv['unread_count'] > 0 ? 'msg-conv-unread' : ''; ?>">
                        <div class="msg-conv-avatar">
                            <?php if ($conv['avatar'] && $conv['avatar'] !== 'default.png'): ?>
                                <img src="<?php echo BASE_URL . '/assets/images/' . e($conv['avatar']); ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        <div class="msg-conv-info">
                            <div class="msg-conv-name">
                                <?php echo e($conv['full_name']); ?>
                                <?php if ($conv['role'] === 'admin'): ?>
                                    <span class="badge badge-admin" style="font-size:0.6rem;padding:2px 6px;">Admin</span>
                                <?php endif; ?>
                            </div>
                            <div class="msg-conv-preview"><?php echo e(mb_strimwidth($conv['last_message'] ?? '', 0, 50, '...')); ?></div>
                        </div>
                        <div class="msg-conv-meta">
                            <span class="msg-conv-time"><?php echo $conv['last_message_date'] ? timeAgo($conv['last_message_date']) : ''; ?></span>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="msg-conv-badge"><?php echo $conv['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php if (empty($conversations)): ?>
                    <div class="empty-state" style="padding:30px;">
                        <i class="fas fa-comment-slash" style="font-size:2rem;"></i>
                        <p>Aucune conversation</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vue conversation -->
            <div class="msg-main">
                <?php if ($conversationWith): ?>
                    <div class="msg-main-header">
                        <div class="msg-main-user">
                            <div class="msg-conv-avatar">
                                <?php if ($conversationWith['avatar'] && $conversationWith['avatar'] !== 'default.png'): ?>
                                    <img src="<?php echo BASE_URL . '/assets/images/' . e($conversationWith['avatar']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h3><?php echo e($conversationWith['full_name']); ?></h3>
                                <?php if ($conversationWith['role'] === 'admin'): ?>
                                    <span class="badge badge-admin">Administrateur</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="msg-messages" id="msgMessages">
                        <?php foreach ($conversationMessages as $msg): ?>
                        <div class="msg-bubble <?php echo $msg['sender_id'] == $userId ? 'msg-sent' : 'msg-received'; ?>">
                            <?php if ($msg['subject']): ?>
                                <div class="msg-subject"><?php echo e($msg['subject']); ?></div>
                            <?php endif; ?>
                            <div class="msg-text"><?php echo nl2br(e($msg['content'])); ?></div>
                            <div class="msg-time"><?php echo date('d/m H:i', strtotime($msg['created_at'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="msg-compose">
                        <form method="POST">
                            <input type="hidden" name="action" value="send">
                            <input type="hidden" name="receiver_id" value="<?php echo $conversationWith['id']; ?>">
                            <div class="msg-compose-inner">
                                <textarea name="content" class="form-control" placeholder="Écrire un message..." required style="min-height:60px;"></textarea>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="msg-empty">
                        <i class="fas fa-comments"></i>
                        <h3>Sélectionnez une conversation</h3>
                        <p>Choisissez une conversation dans la liste ou créez un nouveau message.</p>
                        <button class="btn btn-primary" onclick="openModal('newMsgModal')"><i class="fas fa-plus"></i> Nouveau message</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Modal Nouveau Message -->
<div class="modal-overlay" id="newMsgModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Nouveau Message</h3>
            <button class="modal-close" onclick="closeModal('newMsgModal')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="send">
                <div class="form-group">
                    <label>Destinataire</label>
                    <select name="receiver_id" class="form-control" required>
                        <option value="">Choisir un destinataire...</option>
                        <?php foreach ($adminsList as $admin): ?>
                            <option value="<?php echo $admin['id']; ?>"><?php echo e($admin['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sujet (optionnel)</label>
                    <input type="text" name="subject" class="form-control" placeholder="Sujet du message">
                </div>
                <div class="form-group">
                    <label>Message</label>
                    <textarea name="content" class="form-control" placeholder="Écrire votre message..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newMsgModal')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Auto-scroll to bottom of messages
document.addEventListener('DOMContentLoaded', function() {
    var msgContainer = document.getElementById('msgMessages');
    if (msgContainer) {
        msgContainer.scrollTop = msgContainer.scrollHeight;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
