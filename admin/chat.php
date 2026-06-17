<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdmin();

// Delete single message
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$id]);
    header('Location: chat.php?msg=deleted');
    exit;
}

// Delete all messages
if (isset($_GET['clear_all'])) {
    $pdo->query("DELETE FROM chat_messages");
    header('Location: chat.php?msg=cleared');
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE u.name LIKE ? OR cm.message LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT cm.*, u.name, u.email
    FROM chat_messages cm
    JOIN users u ON u.id = cm.user_id
    $where
    ORDER BY cm.created_at DESC
");
$stmt->execute($params);
$messages = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-chat-fill text-warning"></i> Manage Chat Messages</h4>
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="chat.php?clear_all=1"
               class="btn btn-sm btn-danger"
               onclick="return confirm('Delete ALL chat messages? This cannot be undone.')">
                <i class="bi bi-trash"></i> Clear All
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 small">
            <?= $_GET['msg'] === 'cleared' ? 'All messages cleared.' : 'Message deleted.' ?>
        </div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by user or message..."
                   value="<?= $search ?>">
            <button class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
                <a href="chat.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Stats row -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <h4 class="fw-bold text-warning mb-0"><?= count($messages) ?></h4>
                <p class="text-muted small mb-0">Total Messages</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <?php
                $uniqueUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM chat_messages")->fetchColumn();
                ?>
                <h4 class="fw-bold text-primary mb-0"><?= $uniqueUsers ?></h4>
                <p class="text-muted small mb-0">Active Chatters</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3 text-center">
                <?php
                $todayCount = $pdo->query("SELECT COUNT(*) FROM chat_messages WHERE DATE(created_at) = CURDATE()")->fetchColumn();
                ?>
                <h4 class="fw-bold text-success mb-0"><?= $todayCount ?></h4>
                <p class="text-muted small mb-0">Messages Today</p>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <span class="fw-semibold"><?= count($messages) ?> messages</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>Sent At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $i => $m): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td class="small fw-semibold"><?= sanitize($m['name']) ?></td>
                        <td class="small text-muted"><?= sanitize($m['email']) ?></td>
                        <td class="small" style="max-width:300px">
                            <?= sanitize($m['message']) ?>
                        </td>
                        <td class="small text-muted">
                            <?= date('M j, Y g:i A', strtotime($m['created_at'])) ?>
                        </td>
                        <td>
                            <a href="chat.php?delete=<?= $m['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this message?')">
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

<?php include '../includes/footer.php'; ?>