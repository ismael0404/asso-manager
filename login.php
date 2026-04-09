<?php
/**
 * Page de connexion
 * Fichier: login.php
 */
$pageTitle = 'Connexion';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        setFlash('error', 'Veuillez remplir tous les champs.');
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Vérifier si le compte est actif
            if (!$user['is_active']) {
                setFlash('error', 'Votre compte a été désactivé. Contactez l\'administrateur.');
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['full_name'];
                
                addLog('s\'est connecté', 'user', $user['id']);
                setFlash('success', 'Connexion réussie ! Bienvenue ' . $user['full_name']);
                
                if ($user['role'] === 'admin') {
                    header('Location: ' . BASE_URL . '/admin/dashboard.php');
                } else {
                    header('Location: ' . BASE_URL . '/user/dashboard.php');
                }
                exit();
            }
        } else {
            setFlash('error', 'Identifiants incorrects.');
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <h2>Connexion</h2>
            <p>Accédez à votre espace personnel</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Nom d'utilisateur ou Email</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Entrez votre identifiant" required
                       value="<?php echo e($_POST['username'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Entrez votre mot de passe" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-full btn-lg">
                <i class="fas fa-sign-in-alt"></i> Se connecter
            </button>
        </form>
        
        <div class="auth-footer">
            <p>Pas encore de compte ? <a href="<?php echo BASE_URL; ?>/register.php">Créer un compte</a></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
