<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdmin();

// Delete user
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id !== $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        header('Location: users.php?msg=deleted');
        exit;
    }
}

// Change role function
function changeUserRole($pdo, $id, $newRole) {
    $allowedRoles = ['user', 'admin', 'verified', 'premium'];
    if (in_array($newRole, $allowedRoles)) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $id]);
        return true;
    }
    return false;
}

// Promote to admin
if (isset($_GET['promote'])) {
    $id = intval($_GET['promote']);
    if ($id !== $_SESSION['user_id']) {
        changeUserRole($pdo, $id, 'admin');
        header('Location: users.php?msg=promoted');
        exit;
    }
}

// Demote to user
if (isset($_GET['demote'])) {
    $id = intval($_GET['demote']);
    if ($id !== $_SESSION['user_id']) {
        changeUserRole($pdo, $id, 'user');
        header('Location: users.php?msg=demoted');
        exit;
    }
}

// Set as verified
if (isset($_GET['set_verified'])) {
    $id = intval($_GET['set_verified']);
    if ($id !== $_SESSION['user_id']) {
        changeUserRole($pdo, $id, 'verified');
        header('Location: users.php?msg=verified');
        exit;
    }
}

// Set as premium
if (isset($_GET['set_premium'])) {
    $id = intval($_GET['set_premium']);
    if ($id !== $_SESSION['user_id']) {
        changeUserRole($pdo, $id, 'premium');
        header('Location: users.php?msg=premium');
        exit;
    }
}

// Search
$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE u.name LIKE ? OR u.email LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT u.*, p.profile_image, p.batch, p.job_title,
           (SELECT COUNT(*) FROM posts WHERE user_id = u.id) as post_count
    FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    $where
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Role badge colors
function getRoleBadge($role) {
    switch($role) {
        case 'admin':
            return '<span class="badge bg-danger">👑 Admin</span>';
        case 'premium':
            return '<span class="badge bg-warning text-dark">⭐ Premium</span>';
        case 'verified':
            return '<span class="badge bg-info">✅ Verified</span>';
        default:
            return '<span class="badge bg-secondary">👤 User</span>';
    }
}
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-people text-danger"></i> Manage Users</h4>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 small">
            <?php 
                $msg = $_GET['msg'];
                if ($msg === 'deleted') echo '✅ User deleted successfully.';
                elseif ($msg === 'promoted') echo '👑 User promoted to Admin.';
                elseif ($msg === 'demoted') echo '👤 User demoted to regular User.';
                elseif ($msg === 'verified') echo '✅ User marked as Verified.';
                elseif ($msg === 'premium') echo '⭐ User upgraded to Premium.';
            ?>
        </div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card p-3 mb-4">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by name or email..."
                   value="<?= $search ?>">
            <button class="btn btn-primary px-4">
                <i class="bi bi-search"></i>
            </button>
            <?php if ($search): ?>
                <a href="users.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <span class="fw-semibold"><?= count($users) ?> users found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Batch</th>
                        <th>Posts</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $i => $u): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <img src="../uploads/profiles/<?= $u['profile_image'] ?? 'default.png' ?>"
                                     style="width:36px;height:36px;border-radius:50%;object-fit:cover"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['name']) ?>&background=e94560&color=fff&size=36'">
                                <div>
                                    <div class="fw-semibold small"><?= sanitize($u['name']) ?></div>
                                    <div class="text-muted" style="font-size:11px">
                                        <?= sanitize($u['job_title'] ?? 'BSCS Alumni') ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="small text-muted"><?= sanitize($u['email']) ?></td>
                        <td class="small"><?= sanitize($u['batch'] ?? '-') ?></td>
                        <td class="small"><?= $u['post_count'] ?></td>
                        <td><?= getRoleBadge($u['role']) ?></td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="../profile.php?id=<?= $u['id'] ?>"
                                   class="btn btn-sm btn-outline-primary" title="View Profile">
                                    <i class="bi bi-eye"></i>
                                </a>
                                
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                    <!-- Set as Admin -->
                                    <?php if ($u['role'] !== 'admin'): ?>
                                        <a href="users.php?promote=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           title="Make Admin"
                                           onclick="return confirm('Make this user an Admin?')">
                                            <i class="bi bi-shield-lock"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="users.php?demote=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="Remove Admin"
                                           onclick="return confirm('Remove admin privileges?')">
                                            <i class="bi bi-shield-shaded"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Set as Verified -->
                                    <?php if ($u['role'] !== 'verified' && $u['role'] !== 'premium'): ?>
                                        <a href="users.php?set_verified=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-info"
                                           title="Mark as Verified"
                                           onclick="return confirm('Mark this user as Verified?')">
                                            <i class="bi bi-patch-check"></i>
                                        </a>
                                    <?php elseif ($u['role'] === 'verified'): ?>
                                        <a href="users.php?demote=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-warning"
                                           title="Remove Verified"
                                           onclick="return confirm('Remove verified status?')">
                                            <i class="bi bi-patch-exclamation"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Set as Premium -->
                                    <?php if ($u['role'] !== 'premium'): ?>
                                        <a href="users.php?set_premium=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-warning"
                                           title="Make Premium"
                                           onclick="return confirm('Upgrade this user to Premium?')">
                                            <i class="bi bi-star"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="users.php?demote=<?= $u['id'] ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           title="Remove Premium"
                                           onclick="return confirm('Remove premium status?')">
                                            <i class="bi bi-dash-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <!-- Delete User -->
                                    <a href="users.php?delete=<?= $u['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       title="Delete User"
                                       onclick="return confirm('Delete this user and all their data?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>