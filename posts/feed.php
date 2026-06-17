<?php
require_once '../config/database.php';
require_once '../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];
$error   = '';

// Check user role for posting permission
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userRole = $stmt->fetchColumn();

$canPost = ($userRole === 'premium' || $userRole === 'verified' || $userRole === 'admin');

// Create post (only if user has permission)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'create_post') {
        if (!$canPost) {
            $error = "Only Premium and Verified members can create posts. Upgrade your account to start posting!";
        } else {
            $content = sanitize($_POST['content'] ?? '');
            if (empty($content)) {
                $error = "Please enter some content.";
            } else {
                $image = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = '../uploads/posts/';
                    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                    $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                    $allowed   = ['jpg','jpeg','png','gif','webp'];
                    if (in_array($ext, $allowed) && $_FILES['image']['size'] <= 5 * 1024 * 1024) {
                        $fileName = 'post_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                            $image = $fileName;
                        }
                    } else {
                        $error = "Image must be JPG/PNG/GIF/WEBP and under 5MB.";
                    }
                }
                if (!$error) {
                    $pdo->prepare("INSERT INTO posts (user_id, content, image) VALUES (?,?,?)")
                        ->execute([$userId, $content, $image]);
                    header("Location: feed.php?msg=created");
                    exit;
                }
            }
        }
    }

    if ($_POST['action'] === 'add_comment') {
        $postId  = (int)$_POST['post_id'];
        $comment = sanitize($_POST['comment'] ?? '');
        if (!empty($comment)) {
            $pdo->prepare("INSERT INTO comments (post_id, user_id, comment) VALUES (?,?,?)")
                ->execute([$postId, $userId, $comment]);
        }
        header("Location: feed.php#post-$postId");
        exit;
    }
}

// Delete post
if (isset($_GET['delete'])) {
    $postId = (int)$_GET['delete'];
    $stmt   = $pdo->prepare("SELECT user_id, image FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    if ($post && ($post['user_id'] == $userId || $_SESSION['user_role'] === 'admin')) {
        if ($post['image'] && file_exists('../uploads/posts/' . $post['image']))
            unlink('../uploads/posts/' . $post['image']);
        $pdo->prepare("DELETE FROM likes    WHERE post_id = ?")->execute([$postId]);
        $pdo->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$postId]);
        $pdo->prepare("DELETE FROM posts    WHERE id      = ?")->execute([$postId]);
        header("Location: feed.php?msg=deleted");
        exit;
    }
}

// Delete comment
if (isset($_GET['delete_comment'])) {
    $cid  = (int)$_GET['delete_comment'];
    $stmt = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->execute([$cid]);
    $c = $stmt->fetch();
    if ($c && ($c['user_id'] == $userId || $_SESSION['user_role'] === 'admin')) {
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$cid]);
    }
    header("Location: feed.php");
    exit;
}

// Pagination
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 8;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.id as author_id, u.role as author_role,
           pr.profile_image, pr.job_title, pr.company,
           (SELECT COUNT(*) FROM likes    WHERE post_id = p.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comment_count,
           (SELECT COUNT(*) FROM likes    WHERE post_id = p.id AND user_id = ?) as user_liked
    FROM posts p
    JOIN users u ON u.id = p.user_id
    LEFT JOIN profiles pr ON pr.user_id = p.user_id
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $limit, $offset]);
$posts = $stmt->fetchAll();

$totalPosts = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalPages = ceil($totalPosts / $limit);

// Current user profile
$stmt = $pdo->prepare("
    SELECT u.*, p.profile_image, p.job_title, p.batch
    FROM users u LEFT JOIN profiles p ON p.user_id = u.id WHERE u.id = ?
");
$stmt->execute([$userId]);
$me = $stmt->fetch();
?>
<?php include '../includes/header.php'; ?>

<style>
.feed-col   { max-width: 680px; }
.post-card  { border-radius: 14px; margin-bottom: 16px; }
.post-card .card-body { padding: 18px; }
.post-img   { width:100%; max-height:380px; object-fit:cover; border-radius:10px; }
.comment-box { background:#f8fafc; border-radius:10px; padding:12px; margin-top:12px; }
.comment-item { padding:8px 0; border-bottom:1px solid #f0f0f0; }
.comment-item:last-child { border-bottom:none; }
.stat-pill  { font-size:12px; color:#6b7280; }
.compose-bar { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px;
               padding: 10px 16px; color:#9ca3af; cursor:pointer; font-size:14px;
               width:100%; text-align:left; transition:.2s; }
.compose-bar:hover { background:#f1f5f9; color:#6b7280; }
.compose-bar-disabled { background:#f1f5f9; border:1px solid #e5e7eb; border-radius:12px;
               padding: 10px 16px; color:#9ca3af; font-size:14px; width:100%; 
               text-align:left; cursor:not-allowed; opacity:0.7; }
.badge-batch { background:#fef3c7; color:#92400e; font-size:10px;
               padding:2px 8px; border-radius:20px; font-weight:500; }
.like-btn   { border-radius:10px; font-size:13px; font-weight:600; }
.comment-avatar { width:30px; height:30px; border-radius:50%; object-fit:cover; flex-shrink:0; }
.upgrade-banner { background: linear-gradient(135deg, #fef9e3, #fef3c7); 
                  border: 1px solid #ffd700; border-radius: 12px; padding: 12px 16px;
                  margin-bottom: 16px; display: flex; align-items: center;
                  justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.upgrade-banner p { margin: 0; font-size: 13px; color: #b45309; }
.upgrade-banner .btn-upgrade { background: linear-gradient(135deg, #ffd700, #ffa500); 
                               color: #000; border: none; border-radius: 20px;
                               padding: 5px 15px; font-size: 12px; font-weight: 600; }
</style>

<div class="container">
<div class="row justify-content-center g-4">

    <!-- Left: user mini card -->
    <div class="col-lg-3 d-none d-lg-block">
        <div class="card p-3 text-center sticky-top" style="top:80px">
            <img src="../uploads/profiles/<?= $me['profile_image'] ?? 'default.png' ?>"
                 style="width:70px;height:70px;border-radius:50%;object-fit:cover;margin:0 auto 8px;border:3px solid #e94560"
                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($me['name']) ?>&background=e94560&color=fff&size=70'">
            <h6 class="fw-bold mb-0"><?= sanitize($me['name']) ?></h6>
            <p class="text-muted mb-1" style="font-size:12px">
                <?= sanitize($me['job_title'] ?? 'BSCS Alumni') ?>
            </p>
            <?php if (!empty($me['batch'])): ?>
                <span class="badge-batch">Batch <?= sanitize($me['batch']) ?></span>
            <?php endif; ?>
            <hr class="my-2">
            
            <!-- Show user role badge -->
            <?php if ($userRole === 'premium'): ?>
                <span class="badge bg-warning text-dark mb-2"><i class="bi bi-star-fill"></i> Premium Member</span>
            <?php elseif ($userRole === 'verified'): ?>
                <span class="badge bg-info mb-2"><i class="bi bi-patch-check-fill"></i> Verified Member</span>
            <?php elseif ($userRole === 'admin'): ?>
                <span class="badge bg-danger mb-2"><i class="bi bi-shield-lock-fill"></i> Admin</span>
            <?php else: ?>
                <span class="badge bg-secondary mb-2"><i class="bi bi-person-fill"></i> Regular Member</span>
            <?php endif; ?>
            
            <div class="d-flex justify-content-around text-center">
                <div>
                    <div class="fw-bold text-danger" style="font-size:16px">
                        <?= $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id=?") ? (function() use ($pdo,$userId){ $s=$pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id=?"); $s->execute([$userId]); return $s->fetchColumn(); })() : 0 ?>
                    </div>
                    <div class="text-muted" style="font-size:11px">Posts</div>
                </div>
                <div>
                    <div class="fw-bold text-danger" style="font-size:16px">
                        <?= (function() use ($pdo,$userId){ $s=$pdo->prepare("SELECT COUNT(*) FROM likes l JOIN posts p ON p.id=l.post_id WHERE p.user_id=?"); $s->execute([$userId]); return $s->fetchColumn(); })() ?>
                    </div>
                    <div class="text-muted" style="font-size:11px">Likes</div>
                </div>
            </div>
            <a href="../profile.php?id=<?= $userId ?>" class="btn btn-outline-danger btn-sm mt-2 w-100">
                View Profile
            </a>
        </div>
    </div>

    <!-- Center: feed -->
    <div class="col-lg-6 col-md-10 feed-col">

        <?php if (isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 small">
                <?= $_GET['msg'] === 'created' ? '✅ Post published!' : '🗑️ Post deleted.' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= $error ?></div>
        <?php endif; ?>

        <!-- Upgrade Banner for Regular Users -->
        <?php if (!$canPost && $userRole === 'user'): ?>
            <div class="upgrade-banner">
                <p><i class="bi bi-star-fill me-1" style="color: #ffd700;"></i> <strong>Upgrade to Premium</strong> to create posts and access exclusive features!</p>
                <a href="../premium-upgrade.php" class="btn-upgrade">Upgrade Now →</a>
            </div>
        <?php endif; ?>

        <!-- Compose box (only if user can post) -->
        <div class="card post-card mb-4">
            <div class="card-body">
                <div class="d-flex gap-3 align-items-center">
                    <img src="../uploads/profiles/<?= $me['profile_image'] ?? 'default.png' ?>"
                         class="avatar"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($me['name']) ?>&background=e94560&color=fff'">
                    
                    <?php if ($canPost): ?>
                        <button class="compose-bar" data-bs-toggle="modal" data-bs-target="#postModal">
                            <i class="bi bi-pencil me-2"></i>
                            What's on your mind, <?= explode(' ', sanitize($me['name']))[0] ?>?
                        </button>
                    <?php else: ?>
                        <div class="compose-bar-disabled">
                            <i class="bi bi-lock-fill me-2"></i>
                            Only Premium and Verified members can create posts. 
                            <a href="../premium-upgrade.php" style="color: #e94560;">Upgrade to post →</a>
                        </div>
                    <?php endif; ?>
                </div>
              <hr>

        <!-- Posts -->
        <?php if (empty($posts)): ?>
            <div class="card p-5 text-center">
                <i class="bi bi-newspaper text-muted" style="font-size:3rem"></i>
                <h5 class="mt-3">No posts yet</h5>
                <p class="text-muted small">Be the first to share something!</p>
                <?php if ($canPost): ?>
                    <button class="btn btn-primary mx-auto" style="width:fit-content"
                            data-bs-toggle="modal" data-bs-target="#postModal">
                        Create Post
                    </button>
                <?php else: ?>
                    <a href="../premium-upgrade.php" class="btn btn-warning mx-auto" style="width:fit-content">
                        <i class="bi bi-star-fill me-1"></i> Upgrade to Post
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
            <div class="card post-card" id="post-<?= $post['id'] ?>">
                <div class="card-body">

                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex gap-3 align-items-center">
                            <a href="../profile.php?id=<?= $post['author_id'] ?>">
                                <img src="../uploads/profiles/<?= $post['profile_image'] ?? 'default.png' ?>"
                                     class="avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($post['author_name']) ?>&background=e94560&color=fff'">
                            </a>
                            <div>
                                <a href="../profile.php?id=<?= $post['author_id'] ?>"
                                   class="fw-bold text-dark" style="font-size:14px">
                                    <?= sanitize($post['author_name']) ?>
                                </a>
                                <?php if ($post['author_role'] === 'admin'): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:9px">Admin</span>
                                <?php elseif ($post['author_role'] === 'premium'): ?>
                                    <span class="badge bg-warning text-dark ms-1" style="font-size:9px"><i class="bi bi-star-fill"></i> Premium</span>
                                <?php elseif ($post['author_role'] === 'verified'): ?>
                                    <span class="badge bg-info ms-1" style="font-size:9px"><i class="bi bi-patch-check-fill"></i> Verified</span>
                                <?php endif; ?>
                                <div class="text-muted" style="font-size:11px">
                                    <?php if ($post['job_title']): ?>
                                        <?= sanitize($post['job_title']) ?>
                                        <?php if ($post['company']): ?>
                                            @ <?= sanitize($post['company']) ?>
                                        <?php endif; ?>
                                        &bull;
                                    <?php endif; ?>
                                    <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php if ($post['author_id'] == $userId || $_SESSION['user_role'] === 'admin'): ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-link text-muted p-0"
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <a class="dropdown-item text-danger small"
                                       href="feed.php?delete=<?= $post['id'] ?>"
                                       onclick="return confirm('Delete this post?')">
                                        <i class="bi bi-trash me-2"></i>Delete Post
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <p class="mb-3" style="font-size:14px;line-height:1.65">
                        <?= nl2br(sanitize($post['content'])) ?>
                    </p>

                    <!-- Image -->
                    <?php if ($post['image'] && file_exists('../uploads/posts/' . $post['image'])): ?>
                        <img src="../uploads/posts/<?= $post['image'] ?>"
                             class="post-img mb-3"
                             onclick="openImg('../uploads/posts/<?= $post['image'] ?>')"
                             style="cursor:zoom-in">
                    <?php endif; ?>

                    <!-- Stats bar -->
                    <div class="d-flex justify-content-between stat-pill py-2 border-top border-bottom mb-3">
                        <span>
                            <i class="bi bi-heart-fill text-danger me-1"></i>
                            <span class="like-count-<?= $post['id'] ?>"><?= $post['like_count'] ?></span> likes
                        </span>
                        <span>
                            <i class="bi bi-chat me-1"></i>
                            <?= $post['comment_count'] ?> comments
                        </span>
                    </div>

                    <!-- Action buttons -->
                    <div class="d-flex gap-2 mb-3">
                        <button class="btn btn-sm w-50 like-btn <?= $post['user_liked'] ? 'btn-danger' : 'btn-outline-danger' ?>"
                                id="like-btn-<?= $post['id'] ?>"
                                onclick="toggleLike(<?= $post['id'] ?>)">
                            <i class="bi bi-heart<?= $post['user_liked'] ? '-fill' : '' ?>"
                               id="like-icon-<?= $post['id'] ?>"></i>
                            <span id="like-text-<?= $post['id'] ?>">
                                <?= $post['user_liked'] ? 'Liked' : 'Like' ?>
                            </span>
                        </button>
                        <button class="btn btn-sm btn-outline-secondary w-50 like-btn"
                                onclick="toggleComments(<?= $post['id'] ?>)">
                            <i class="bi bi-chat me-1"></i> Comment
                        </button>
                    </div>

                    <!-- Comment input -->
                    <div id="comment-input-<?= $post['id'] ?>" style="display:none">
                        <form method="POST">
                            <input type="hidden" name="action"  value="add_comment">
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <div class="d-flex gap-2">
                                <img src="../uploads/profiles/<?= $me['profile_image'] ?? 'default.png' ?>"
                                     class="comment-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($me['name']) ?>&background=e94560&color=fff'">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="comment" class="form-control"
                                           placeholder="Write a comment..." required
                                           style="border-radius:20px 0 0 20px">
                                    <button class="btn btn-danger" style="border-radius:0 20px 20px 0">
                                        <i class="bi bi-send-fill"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Comments list -->
                    <?php
                    $cs = $pdo->prepare("
                        SELECT c.*, u.name, u.id as uid, pr.profile_image
                        FROM comments c
                        JOIN users u ON u.id = c.user_id
                        LEFT JOIN profiles pr ON pr.user_id = u.id
                        WHERE c.post_id = ? ORDER BY c.created_at ASC
                    ");
                    $cs->execute([$post['id']]);
                    $comments = $cs->fetchAll();
                    ?>
                    <?php if (!empty($comments)): ?>
                    <div class="comment-box mt-3">
                        <p class="fw-semibold mb-2" style="font-size:13px">
                            Comments (<?= count($comments) ?>)
                        </p>
                        <?php foreach ($comments as $c): ?>
                        <div class="comment-item d-flex gap-2">
                            <a href="../profile.php?id=<?= $c['uid'] ?>">
                                <img src="../uploads/profiles/<?= $c['profile_image'] ?? 'default.png' ?>"
                                     class="comment-avatar"
                                     onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($c['name']) ?>&background=e94560&color=fff'">
                            </a>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <a href="../profile.php?id=<?= $c['uid'] ?>"
                                           class="fw-semibold text-dark" style="font-size:12px">
                                            <?= sanitize($c['name']) ?>
                                        </a>
                                        <span class="text-muted ms-1" style="font-size:11px">
                                            <?= date('M j, g:i A', strtotime($c['created_at'])) ?>
                                        </span>
                                    </div>
                                    <?php if ($c['uid'] == $userId || $_SESSION['user_role'] === 'admin'): ?>
                                    <a href="feed.php?delete_comment=<?= $c['id'] ?>"
                                       class="text-danger" style="font-size:11px"
                                       onclick="return confirm('Delete comment?')">
                                        <i class="bi bi-x-lg"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-0 mt-1" style="font-size:13px">
                                    <?= nl2br(sanitize($c['comment'])) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav class="mt-3">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page-1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page+1 ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>
</div>

<!-- Create Post Modal (only shown if user can post) -->
<?php if ($canPost): ?>
<div class="modal fade" id="postModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:16px;overflow:hidden">
            <div class="modal-header" style="background:#e94560;border:none">
                <h5 class="modal-title text-white fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>Create Post
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_post">
                <div class="modal-body">
                    <div class="d-flex gap-3 mb-3">
                        <img src="../uploads/profiles/<?= $me['profile_image'] ?? 'default.png' ?>"
                             class="avatar"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($me['name']) ?>&background=e94560&color=fff'">
                        <div>
                            <div class="fw-bold"><?= sanitize($me['name']) ?></div>
                            <small class="text-muted">BSCS Alumni</small>
                        </div>
                    </div>
                    <textarea name="content" class="form-control border-0 bg-light"
                              rows="5"
                              placeholder="What's on your mind? Share news, achievements, job openings..."
                              style="font-size:15px;resize:none;border-radius:12px"
                              required></textarea>

                    <!-- Image preview -->
                    <div id="imgPreviewWrap" class="mt-3" style="display:none">
                        <img id="imgPreview" src="" class="img-fluid rounded"
                             style="max-height:200px;width:100%;object-fit:cover">
                        <button type="button" onclick="clearImg()"
                                class="btn btn-sm btn-danger mt-1">
                            <i class="bi bi-x"></i> Remove
                        </button>
                    </div>

                    <div class="mt-3 d-flex align-items-center gap-2">
                        <label for="imgInput" class="btn btn-outline-secondary btn-sm"
                               style="border-radius:20px">
                            <i class="bi bi-image me-1"></i> Add Photo
                        </label>
                        <input type="file" name="image" id="imgInput"
                               class="d-none" accept="image/*"
                               onchange="previewImg(this)">
                        <small class="text-muted">Max 5MB</small>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4 fw-semibold">
                        <i class="bi bi-send me-1"></i> Publish
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Image lightbox -->
<div id="lightbox" onclick="this.style.display='none'"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);
            z-index:9999;cursor:zoom-out;display:none;align-items:center;justify-content:center">
    <img id="lightboxImg" src="" style="max-width:90vw;max-height:90vh;border-radius:10px">
</div>

<script>
// Like toggle
function toggleLike(postId) {
    const btn      = document.getElementById('like-btn-' + postId);
    const icon     = document.getElementById('like-icon-' + postId);
    const text     = document.getElementById('like-text-' + postId);
    const countEl  = document.querySelector('.like-count-' + postId);

    fetch('../ajax/like.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'post_id=' + postId
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        countEl.textContent = data.likes;
        if (data.liked) {
            btn.classList.replace('btn-outline-danger','btn-danger');
            icon.classList.replace('bi-heart','bi-heart-fill');
            text.textContent = 'Liked';
        } else {
            btn.classList.replace('btn-danger','btn-outline-danger');
            icon.classList.replace('bi-heart-fill','bi-heart');
            text.textContent = 'Like';
        }
    });
}

// Toggle comment box
function toggleComments(postId) {
    const box = document.getElementById('comment-input-' + postId);
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
    if (box.style.display === 'block') box.querySelector('input').focus();
}

// Image preview in modal
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('imgPreview').src = e.target.result;
            document.getElementById('imgPreviewWrap').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function clearImg() {
    document.getElementById('imgInput').value = '';
    document.getElementById('imgPreviewWrap').style.display = 'none';
}

// Lightbox
function openImg(src) {
    const lb = document.getElementById('lightbox');
    document.getElementById('lightboxImg').src = src;
    lb.style.display = 'flex';
}
</script>

<?php include '../includes/footer.php'; ?>