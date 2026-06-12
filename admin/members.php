<?php
/**
 * Gestion des membres (Admin) v2.0
 * Fichier: admin/members.php
 * Ajout: logs sur ajout/modification/suppression
 */
$pageTitle = 'Gestion des Membres';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $membershipDate = $_POST['membership_date'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'active';
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO members (first_name, last_name, email, phone, address, membership_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $membershipDate, $status]);
            $newId = $pdo->lastInsertId();
            addLog('a ajouté le membre "' . $firstName . ' ' . $lastName . '"', 'member', $newId);
            setFlash('success', 'Membre ajouté avec succès !');
        } else {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE members SET first_name=?, last_name=?, email=?, phone=?, address=?, membership_date=?, status=? WHERE id=?");
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $membershipDate, $status, $id]);
            addLog('a modifié le membre "' . $firstName . ' ' . $lastName . '"', 'member', $id);
            setFlash('success', 'Membre modifié !');
        }
        header('Location: ' . BASE_URL . '/admin/members.php');
        exit();
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM members WHERE id=?");
    $stmt->execute([$id]);
    $memberName = $stmt->fetchColumn();
    
    $pdo->prepare("DELETE FROM members WHERE id=?")->execute([$id]);
    addLog('a supprimé le membre "' . $memberName . '"', 'member', $id);
    setFlash('success', 'Membre supprimé.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit();
}

$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$total = $pdo->query("SELECT COUNT(*) as c FROM members")->fetch()['c'];
$totalPages = ceil($total / $perPage);
$offset = ($page - 1) * $perPage;

$members = $pdo->query("SELECT * FROM members ORDER BY created_at DESC LIMIT $perPage OFFSET $offset")->fetchAll();

$editMember = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editMember = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="admin-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <div>
                <h1><i class="fas fa-users"></i> Membres</h1>
                <p>Gérez les membres de l'association</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('memberModal')">
                <i class="fas fa-user-plus"></i> Nouveau Membre
            </button>
        </div>
        
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo $total; ?> membre(s)</h3>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Inscription</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $i => $m): ?>
                    <tr>
                        <td><?php echo $offset + $i + 1; ?></td>
                        <td><strong><?php echo e($m['first_name'] . ' ' . $m['last_name']); ?></strong></td>
                        <td><?php echo e($m['email']); ?></td>
                        <td><?php echo e($m['phone'] ?? '-'); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($m['membership_date'])); ?></td>
                        <td><span class="badge badge-<?php echo e($m['status']); ?>"><?php echo e(translateStatus($m['status'])); ?></span></td>
                        <td>
                            <div class="table-actions">
                                <a href="?edit=<?php echo $m['id']; ?>" class="btn btn-icon btn-warning"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?php echo $m['id']; ?>" class="btn btn-icon btn-danger" data-confirm="Supprimer ce membre ?"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($members)): ?>
                    <tr><td colspan="7" class="text-center" style="padding:30px;color:var(--text-muted);">Aucun membre</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p == $page): ?><span class="active"><?php echo $p; ?></span>
                    <?php else: ?><a href="?page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?><a href="?page=<?php echo $page+1; ?>"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay <?php echo $editMember ? 'show' : ''; ?>" id="memberModal">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo $editMember ? 'Modifier le Membre' : 'Nouveau Membre'; ?></h3>
            <button class="modal-close" onclick="closeModal('memberModal'); <?php if($editMember) echo 'window.location.href=\'members.php\''; ?>">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="<?php echo $editMember ? 'edit' : 'add'; ?>">
                <?php if ($editMember): ?><input type="hidden" name="id" value="<?php echo $editMember['id']; ?>"><?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="first_name" class="form-control" required value="<?php echo e($editMember['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="last_name" class="form-control" required value="<?php echo e($editMember['last_name'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" class="form-control" required value="<?php echo e($editMember['email'] ?? ''); ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo e($editMember['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Date d'adhésion</label>
                        <input type="date" name="membership_date" class="form-control" value="<?php echo e($editMember['membership_date'] ?? date('Y-m-d')); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <textarea name="address" class="form-control" style="min-height:60px;"><?php echo e($editMember['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <select name="status" class="form-control">
                        <option value="active" <?php echo ($editMember['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="inactive" <?php echo ($editMember['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('memberModal'); <?php if($editMember) echo 'window.location.href=\'members.php\''; ?>">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $editMember ? 'Modifier' : 'Ajouter'; ?></button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
