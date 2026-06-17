<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdmin();

// Delete comment
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$id]);
    header('Location: comments.php?msg=deleted');
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE u.name LIKE ? OR c.comment LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT c.*, u.name as author, p.content as post_content
    FROM comments c
    JOIN users u ON u.id = c.user_id
    JOIN posts p ON p.id = c.post_id
    $where
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$comments = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-chat-dots text-success"></i> Manage Comments</h4>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 small">Comment deleted.</div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by author or comment..."
                   value="<?= $search ?>">
            <button class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
                <a href="comments.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <span class="fw-semibold"><?= count($comments) ?> comments found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Author</th>
                        <th>Comment</th>
                        <th>On Post</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $i => $c): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td class="small fw-semibold"><?= sanitize($c['author']) ?></td>
                        <td class="small" style="max-width:250px">
                            <?= sanitize($c['comment']) ?>
                        </td>
                        <td class="small text-muted" style="max-width:150px">
                            <?= substr(sanitize($c['post_content']), 0, 40) ?>...
                        </td>
                        <td class="small text-muted">
                            <?= date('M j, Y', strtotime($c['created_at'])) ?>
                        </td>
                        <td>
                            <a href="comments.php?delete=<?= $c['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this comment?')">
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