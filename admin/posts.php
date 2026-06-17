<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireAdmin();

// Delete post
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $post = $pdo->prepare("SELECT image FROM posts WHERE id = ?");
    $post->execute([$id]);
    $p = $post->fetch();
    if ($p && $p['image'] && file_exists('../uploads/posts/' . $p['image'])) {
        unlink('../uploads/posts/' . $p['image']);
    }
    $pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);
    header('Location: posts.php?msg=deleted');
    exit;
}

$search = sanitize($_GET['search'] ?? '');
$where  = $search ? "WHERE u.name LIKE ? OR p.content LIKE ?" : "";
$params = $search ? ["%$search%", "%$search%"] : [];

$stmt = $pdo->prepare("
    SELECT p.*, u.name,
           (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) as comment_count
    FROM posts p
    JOIN users u ON u.id = p.user_id
    $where
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$posts = $stmt->fetchAll();
?>
<?php include '../includes/header.php'; ?>

<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0"><i class="bi bi-newspaper text-primary"></i> Manage Posts</h4>
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success py-2 small">Post deleted successfully.</div>
    <?php endif; ?>

    <div class="card p-3 mb-4">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control"
                   placeholder="Search by author or content..."
                   value="<?= $search ?>">
            <button class="btn btn-primary px-4"><i class="bi bi-search"></i></button>
            <?php if ($search): ?>
                <a href="posts.php" class="btn btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <span class="fw-semibold"><?= count($posts) ?> posts found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Author</th>
                        <th>Content</th>
                        <th>Image</th>
                        <th>Likes</th>
                        <th>Comments</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $i => $post): ?>
                    <tr>
                        <td class="text-muted small"><?= $i + 1 ?></td>
                        <td class="small fw-semibold"><?= sanitize($post['name']) ?></td>
                        <td class="small text-muted" style="max-width:200px">
                            <?= substr(sanitize($post['content']), 0, 60) ?>...
                        </td>
                        <td>
                            <?php if ($post['image']): ?>
                                <img src="../uploads/posts/<?= $post['image'] ?>"
                                     style="width:50px;height:40px;object-fit:cover;border-radius:6px">
                            <?php else: ?>
                                <span class="text-muted small">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><i class="bi bi-heart-fill text-danger"></i> <?= $post['like_count'] ?></td>
                        <td class="small"><i class="bi bi-chat text-primary"></i> <?= $post['comment_count'] ?></td>
                        <td class="small text-muted"><?= date('M j, Y', strtotime($post['created_at'])) ?></td>
                        <td>
                            <a href="posts.php?delete=<?= $post['id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               onclick="return confirm('Delete this post and all its comments/likes?')">
                                <i class="bi bi-trash"></i> Delete
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