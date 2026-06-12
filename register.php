<?php
/**
 * Page d'inscription
 * Fichier: register.php
 */
$pageTitle = 'Inscription';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $errors = [];
    if (empty($username))
        $errors[] = 'Le nom d\'utilisateur est requis.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = 'Email invalide.';
    if (empty($fullName))
        $errors[] = 'Le nom complet est requis.';
    if (strlen($password) < 6)
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    if ($password !== $confirmPassword)
        $errors[] = 'Les mots de passe ne correspondent pas.';

    if (empty($errors)) {
        // Vérifier unicité
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce nom d\'utilisateur ou email existe déjà.';
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, 'user')");
        $stmt->execute([$username, $email, $hashedPassword, $fullName, $phone]);

        setFlash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    } else {
        setFlash('error', implode('<br>', $errors));
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="auth-page">
    <div style="display: flex; max-width: 1000px; width: 100%; background: var(--card-bg); border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); overflow: hidden; position: relative; z-index: 1; flex-wrap: wrap;">
        
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
                    <i class="fas fa-user-plus"></i>
                </div>
                <h2>Créer un compte</h2>
                <p>Rejoignez notre communauté</p>
            </div>

            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="username"><i class="fas fa-user"></i> Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="ex: jean.dupont"
                            required value="<?php echo e($_POST['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="full_name"><i class="fas fa-id-card"></i> Nom complet</label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                            placeholder="ex: Jean Dupont" required value="<?php echo e($_POST['full_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="ex: jean@email.com"
                        required value="<?php echo e($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Téléphone (optionnel)</label>
                    <input type="text" id="phone" name="phone" class="form-control" placeholder="ex: +225 07 00 00 00"
                        value="<?php echo e($_POST['phone'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Min. 6 caractères" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirmer</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            placeholder="Confirmer le mot de passe" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full btn-lg">
                    <i class="fas fa-user-plus"></i> S'inscrire
                </button>
            </form>

            <div class="auth-footer">
                <p>Déjà inscrit ? <a href="<?php echo BASE_URL; ?>/login.php">Se connecter</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>