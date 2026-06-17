<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdmin();

// Stats
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$totalPosts    = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalComments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalMessages = $pdo->query("SELECT COUNT(*) FROM chat_messages")->fetchColumn();

// Recent users
$recentUsers = $pdo->query("
    SELECT u.*, p.profile_image FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    ORDER BY u.created_at DESC LIMIT 5
")->fetchAll();

// Recent posts
$recentPosts = $pdo->query("
    SELECT p.*, u.name FROM posts p
    JOIN users u ON u.id = p.user_id
    ORDER BY p.created_at DESC LIMIT 5
")->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid px-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-shield-check text-danger"></i> Admin Dashboard</h4>
        <span class="text-muted small">Welcome, <?= sanitize($_SESSION['user_name']) ?></span>
    </div>

    <!-- Stats cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3 border-start border-danger border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Alumni</p>
                        <h3 class="fw-bold mb-0"><?= $totalUsers ?></h3>
                    </div>
                    <i class="bi bi-people-fill text-danger" style="font-size:2rem"></i>
                </div>
                <a href="users.php" class="text-danger small mt-2 d-block">Manage Users →</a>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3 border-start border-primary border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Posts</p>
                        <h3 class="fw-bold mb-0"><?= $totalPosts ?></h3>
                    </div>
                    <i class="bi bi-newspaper text-primary" style="font-size:2rem"></i>
                </div>
                <a href="posts.php" class="text-primary small mt-2 d-block">Manage Posts →</a>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3 border-start border-success border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Total Comments</p>
                        <h3 class="fw-bold mb-0"><?= $totalComments ?></h3>
                    </div>
                    <i class="bi bi-chat-dots-fill text-success" style="font-size:2rem"></i>
                </div>
                <a href="comments.php" class="text-success small mt-2 d-block">Manage Comments →</a>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card p-3 border-start border-warning border-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small mb-1">Chat Messages</p>
                        <h3 class="fw-bold mb-0"><?= $totalMessages ?></h3>
                    </div>
                    <i class="bi bi-chat-fill text-warning" style="font-size:2rem"></i>
                </div>
                <a href="chat.php" class="text-warning small mt-2 d-block">Manage Chat →</a>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Users -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-people me-2"></i>Recent Users</h6>
                    <a href="users.php" class="btn btn-sm btn-outline-danger">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">User</th>
                                <th class="small">Role</th>
                                <th class="small">Joined</th>
                                <th class="small">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="../uploads/profiles/<?= $u['profile_image'] ?? 'default.png' ?>"
                                             style="width:32px;height:32px;border-radius:50%;object-fit:cover"
                                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($u['name']) ?>&background=e94560&color=fff&size=32'">
                                        <span class="small fw-semibold"><?= sanitize($u['name']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                        <?= $u['role'] ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="users.php?delete=<?= $u['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this user?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-newspaper me-2"></i>Recent Posts</h6>
                    <a href="posts.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="small">Author</th>
                                <th class="small">Content</th>
                                <th class="small">Date</th>
                                <th class="small">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPosts as $post): ?>
                            <tr>
                                <td class="small fw-semibold"><?= sanitize($post['name']) ?></td>
                                <td class="small text-muted">
                                    <?= substr(sanitize($post['content']), 0, 40) ?>...
                                </td>
                                <td class="small text-muted"><?= date('M j', strtotime($post['created_at'])) ?></td>
                                <td>
                                    <a href="posts.php?delete=<?= $post['id'] ?>"
                                       class="btn btn-sm btn-outline-danger"
                                       onclick="return confirm('Delete this post?')">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>