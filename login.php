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
    <div style="display: flex; max-width: 900px; width: 100%; background: var(--card-bg); border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); overflow: hidden; position: relative; z-index: 1; flex-wrap: wrap;">
        
        <!-- Section Image & Slogan (Gauche) -->
        <div style="flex: 1 1 400px; background: var(--primary-bg); display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px; text-align: center;">
            <img src="<?php echo BASE_URL; ?>/assets/images/page.jfif" alt="Illustration" style="max-width: 100%; max-height: 350px; object-fit: contain; margin-bottom: 32px; filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));">
            <h2 style="font-size: 1.8rem; font-weight: 800; margin-bottom: 12px; color: var(--gray-900);">L'union fait la force.</h2>
            <p style="font-size: 1.05rem; color: var(--text-light); max-width: 90%;">Rejoignez une communauté dynamique, participez à nos activités passionnantes et restez au cœur de notre actualité.</p>
        </div>

        <!-- Section Formulaire (Droite) -->
        <div style="flex: 1 1 400px; padding: 48px 40px; display: flex; flex-direction: column; justify-content: center;">
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
                        placeholder="Entrez votre identifiant" required value="<?php echo e($_POST['username'] ?? ''); ?>">
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>