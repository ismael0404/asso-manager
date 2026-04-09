<?php
/**
 * Paramètres Admin
 * Fichier: admin/settings.php
 */
$pageTitle = 'Paramètres';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$currentUser = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
        $stmt->execute([$fullName, $email, $phone, $_SESSION['user_id']]);
        $_SESSION['user_name'] = $fullName;
        setFlash('success', 'Profil mis à jour !');
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit();
    }
    
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPass, $currentUser['password'])) {
            setFlash('error', 'Mot de passe actuel incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('error', 'Le nouveau mot de passe doit contenir au moins 6 caractères.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('error', 'Les mots de passe ne correspondent pas.');
        } else {
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $_SESSION['user_id']]);
            setFlash('success', 'Mot de passe modifié !');
        }
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit();
    }
}

// Refresh user data
$currentUser = getCurrentUser();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-cog"></i> Paramètres</h1>
                <p>Gérez votre profil et vos préférences</p>
            </div>
        </div>
        
        <div class="grid-2">
            <!-- Profil -->
            <div class="profile-card">
                <h3><i class="fas fa-user-edit"></i> Modifier le Profil</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-group">
                        <label>Nom complet</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo e($currentUser['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?php echo e($currentUser['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo e($currentUser['phone'] ?? ''); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </form>
            </div>
            
            <!-- Mot de passe -->
            <div class="profile-card">
                <h3><i class="fas fa-lock"></i> Changer le Mot de Passe</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmer le nouveau mot de passe</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key"></i> Modifier le mot de passe
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
