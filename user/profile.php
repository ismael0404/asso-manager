<?php
/**
 * Profil Utilisateur v3.0
 * Fichier: user/profile.php
 * Ajouts: photo de profil (upload), bio, avatar
 */
$pageTitle = 'Mon Profil';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Upload avatar
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload = uploadImage($_FILES['avatar'], 'avatars');
            if ($upload['success']) {
                $avatarPath = $upload['filename'];
            } else {
                setFlash('error', $upload['message']);
                header('Location: ' . BASE_URL . '/user/profile.php');
                exit();
            }
        }
        
        if ($avatarPath) {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, bio=?, avatar=? WHERE id=?");
            $stmt->execute([$fullName, $email, $phone, $bio, $avatarPath, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=?, bio=? WHERE id=?");
            $stmt->execute([$fullName, $email, $phone, $bio, $_SESSION['user_id']]);
        }
        $_SESSION['user_name'] = $fullName;
        addLog('a mis à jour son profil', 'user', $_SESSION['user_id']);
        setFlash('success', 'Profil mis à jour !');
        header('Location: ' . BASE_URL . '/user/profile.php');
        exit();
    }
    
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPass, $currentUser['password'])) {
            setFlash('error', 'Mot de passe actuel incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('error', 'Les mots de passe ne correspondent pas.');
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $_SESSION['user_id']]);
            addLog('a changé son mot de passe', 'user', $_SESSION['user_id']);
            setFlash('success', 'Mot de passe modifié !');
        }
        header('Location: ' . BASE_URL . '/user/profile.php');
        exit();
    }
    
    if ($action === 'remove_avatar') {
        $pdo->prepare("UPDATE users SET avatar='default.png' WHERE id=?")->execute([$_SESSION['user_id']]);
        setFlash('success', 'Photo de profil supprimée.');
        header('Location: ' . BASE_URL . '/user/profile.php');
        exit();
    }
}

$currentUser = getCurrentUser();

// Stats du profil
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM participations WHERE user_id = ? AND status = 'accepted'");
$stmt->execute([$_SESSION['user_id']]);
$stats['activities'] = $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM comments WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['comments'] = $stmt->fetch()['c'];

$stmt = $pdo->prepare("SELECT COUNT(*) as c FROM favorites WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['favorites'] = $stmt->fetch()['c'];

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Profile Header -->
<div class="profile-header">
    <div class="profile-avatar">
        <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png'): ?>
            <img src="<?php echo BASE_URL . '/assets/images/' . e($currentUser['avatar']); ?>" alt="Avatar" class="profile-avatar-img">
        <?php else: ?>
            <i class="fas fa-user"></i>
        <?php endif; ?>
    </div>
    <h2><?php echo e($currentUser['full_name']); ?></h2>
    <p><?php echo e($currentUser['email']); ?> · <span class="badge badge-<?php echo $currentUser['role']; ?>"><?php echo translateStatus($currentUser['role']); ?></span></p>
    <?php if ($currentUser['bio']): ?>
        <p class="profile-bio-hero"><?php echo e($currentUser['bio']); ?></p>
    <?php endif; ?>
    
    <!-- Mini stats -->
    <div class="profile-mini-stats">
        <div class="pms-item">
            <span class="pms-count"><?php echo $stats['activities']; ?></span>
            <span class="pms-label">Activités</span>
        </div>
        <div class="pms-item">
            <span class="pms-count"><?php echo $stats['comments']; ?></span>
            <span class="pms-label">Commentaires</span>
        </div>
        <div class="pms-item">
            <span class="pms-count"><?php echo $stats['favorites']; ?></span>
            <span class="pms-label">Favoris</span>
        </div>
    </div>
</div>

<!-- Profile Content -->
<div class="profile-content">
    <!-- Informations -->
    <div class="profile-card">
        <h3><i class="fas fa-info-circle"></i> Informations Personnelles</h3>
        <div class="profile-info-grid">
            <div class="profile-info-item">
                <label>Nom complet</label>
                <p><?php echo e($currentUser['full_name']); ?></p>
            </div>
            <div class="profile-info-item">
                <label>Nom d'utilisateur</label>
                <p><?php echo e($currentUser['username']); ?></p>
            </div>
            <div class="profile-info-item">
                <label>Email</label>
                <p><?php echo e($currentUser['email']); ?></p>
            </div>
            <div class="profile-info-item">
                <label>Téléphone</label>
                <p><?php echo e($currentUser['phone'] ?? 'Non renseigné'); ?></p>
            </div>
            <div class="profile-info-item">
                <label>Rôle</label>
                <p><span class="badge badge-<?php echo $currentUser['role']; ?>"><?php echo translateStatus($currentUser['role']); ?></span></p>
            </div>
            <div class="profile-info-item">
                <label>Membre depuis</label>
                <p><?php echo date('d/m/Y', strtotime($currentUser['created_at'])); ?></p>
            </div>
        </div>
        <?php if ($currentUser['bio']): ?>
        <div class="profile-info-item mt-2" style="grid-column:1/-1;">
            <label>Biographie</label>
            <p><?php echo nl2br(e($currentUser['bio'])); ?></p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modifier le profil -->
    <div class="profile-card">
        <h3><i class="fas fa-user-edit"></i> Modifier le Profil</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">
            
            <!-- Photo de profil -->
            <div class="form-group">
                <label>Photo de profil</label>
                <div class="avatar-upload-area">
                    <div class="avatar-preview" id="avatarPreview">
                        <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png'): ?>
                            <img src="<?php echo BASE_URL . '/assets/images/' . e($currentUser['avatar']); ?>" alt="Avatar" id="avatarImg">
                        <?php else: ?>
                            <i class="fas fa-camera" id="avatarIcon"></i>
                            <img src="" alt="" id="avatarImg" style="display:none;">
                        <?php endif; ?>
                    </div>
                    <div class="avatar-upload-actions">
                        <label class="btn btn-sm btn-secondary" for="avatarInput">
                            <i class="fas fa-upload"></i> Changer la photo
                        </label>
                        <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                        <?php if ($currentUser['avatar'] && $currentUser['avatar'] !== 'default.png'): ?>
                        <button type="button" class="btn btn-sm btn-danger" onclick="document.getElementById('removeAvatarForm').submit()">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                        <?php endif; ?>
                        <small style="color:var(--text-muted);">JPG, PNG, GIF, WEBP - Max 5MB</small>
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Nom complet</label>
                    <input type="text" name="full_name" class="form-control" value="<?php echo e($currentUser['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo e($currentUser['email']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo e($currentUser['phone'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Biographie</label>
                <textarea name="bio" class="form-control" placeholder="Parlez-nous de vous..." style="min-height:100px;"><?php echo e($currentUser['bio'] ?? ''); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>
    
    <!-- Supprimer avatar (form caché) -->
    <form id="removeAvatarForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="remove_avatar">
    </form>
    
    <!-- Mot de passe -->
    <div class="profile-card">
        <h3><i class="fas fa-lock"></i> Changer le Mot de Passe</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Mot de passe actuel</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Nouveau mot de passe</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Confirmer</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Modifier</button>
        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('avatarImg');
            var icon = document.getElementById('avatarIcon');
            img.src = e.target.result;
            img.style.display = 'block';
            if (icon) icon.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
