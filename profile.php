<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

$profile_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profile_id === 0 && isLoggedIn()) {
    $profile_id = $_SESSION['user_id'];
}

if ($profile_id === 0) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.role, u.created_at,
           COALESCE(p.batch,        '') as batch,
           COALESCE(p.session,      '') as session,
           COALESCE(p.degree,       '') as degree,
           COALESCE(p.company,      '') as company,
           COALESCE(p.job_title,    '') as job_title,
           COALESCE(p.phone,        '') as phone,
           COALESCE(p.linkedin_url, '') as linkedin_url,
           COALESCE(p.bio,          '') as bio,
           COALESCE(p.profile_image,'default.png') as profile_image,
           COALESCE(p.skills,       '') as skills
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$profile_id]);
$pu = $stmt->fetch();

if (!$pu) {
    header('Location: ' . BASE_URL . '/alumni-directory.php');
    exit;
}

$isOwn = isLoggedIn() && $_SESSION['user_id'] == $pu['id'];

// Skills array
$skills = $pu['skills']
    ? array_filter(array_map('trim', explode(',', $pu['skills'])))
    : [];

// Stats
$postCount = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id=?");
$postCount->execute([$pu['id']]);
$postCount = $postCount->fetchColumn();

$likeCount = $pdo->prepare("
    SELECT COUNT(*) FROM likes l JOIN posts p ON p.id=l.post_id WHERE p.user_id=?
");
$likeCount->execute([$pu['id']]);
$likeCount = $likeCount->fetchColumn();

$commentCount = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_id=?");
$commentCount->execute([$pu['id']]);
$commentCount = $commentCount->fetchColumn();

// Member duration
$joined      = new DateTime($pu['created_at']);
$now         = new DateTime();
$diff        = $joined->diff($now);
$memberSince = $diff->y > 0 ? $diff->y . ' yr' . ($diff->y > 1 ? 's' : '') : $diff->m . ' mo';

// Recent posts
$rp = $pdo->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM likes    WHERE post_id=p.id) as likes,
           (SELECT COUNT(*) FROM comments WHERE post_id=p.id) as comments
    FROM posts p WHERE p.user_id=? ORDER BY p.created_at DESC LIMIT 3
");
$rp->execute([$pu['id']]);
$recentPosts = $rp->fetchAll();

$imgPath = 'uploads/profiles/' . $pu['profile_image'];
$imgUrl  = (file_exists($imgPath) && $pu['profile_image'] !== 'default.png')
    ? BASE_URL . '/' . $imgPath
    : 'https://ui-avatars.com/api/?name=' . urlencode($pu['name']) . '&background=e94560&color=fff&size=170';

// Function to get role badge HTML
function getRoleBadgeHTML($role) {
    switch($role) {
        case 'admin':
            return '<span class="role-badge rb-admin"><i class="bi bi-shield-lock-fill me-1"></i>👑 Admin</span>';
        case 'premium':
            return '<span class="role-badge rb-premium"><i class="bi bi-star-fill me-1"></i> Premium Member</span>';
        case 'verified':
            return '<span class="role-badge rb-verified"><i class="bi bi-patch-check-fill me-1"></i> Verified Member</span>';
        default:
            return '<span class="role-badge rb-user"><i class="bi bi-person-fill me-1"></i>🎓 Alumni</span>';
    }
}

// Get verification and premium status for additional badges
$isVerified = ($pu['role'] === 'verified' || $pu['role'] === 'premium' || $pu['role'] === 'admin');
$isPremium = ($pu['role'] === 'premium');
$isAdmin = ($pu['role'] === 'admin');
?>
<?php include 'includes/header.php'; ?>

<style>
.profile-hero   { background: linear-gradient(135deg,#1a1a2e,#16213e);
                  border-radius:16px; padding:32px 24px; color:#fff; margin-bottom:20px; }
.p-avatar       { width:110px; height:110px; border-radius:50%; object-fit:cover;
                  border:4px solid #e94560; flex-shrink:0; }
.info-pill      { background:rgba(255,255,255,.1); border-radius:20px;
                  padding:4px 14px; font-size:12px; display:inline-block; margin:2px; }
.section-card   { border-radius:14px; margin-bottom:16px; overflow:hidden; }
.section-head   { padding:14px 18px; border-bottom:1px solid #f0f0f0;
                  font-size:14px; font-weight:700; }
.section-body   { padding:18px; }
.info-box       { background:#f8fafc; border-left:3px solid #e94560;
                  border-radius:10px; padding:12px 14px; height:100%; }
.info-box .label { font-size:11px; color:#9ca3af; margin-bottom:3px; }
.info-box .val   { font-size:14px; font-weight:600; color:#1f2937; }
.skill-chip     { background:#fce4e8; color:#e94560; border-radius:20px;
                  padding:4px 14px; font-size:12px; font-weight:600;
                  display:inline-block; margin:3px; }
.stat-box       { background:#f8fafc; border-radius:12px; padding:16px 10px;
                  text-align:center; transition:.2s; }
.stat-box:hover { background:#fce4e8; transform:translateY(-2px); }
.stat-val       { font-size:1.6rem; font-weight:900; color:#e94560; line-height:1; }
.stat-lbl       { font-size:11px; color:#9ca3af; margin-top:4px; }
.post-mini      { border:1px solid #f0f0f0; border-radius:10px;
                  padding:12px 14px; margin-bottom:10px; transition:.2s; }
.post-mini:hover { border-color:#e94560; background:#fff9fa; }
.post-mini-img  { width:60px; height:60px; border-radius:8px;
                  object-fit:cover; flex-shrink:0; }

/* Role Badges */
.role-badge     { padding:5px 14px; border-radius:20px; font-size:12px;
                  font-weight:700; display:inline-flex; align-items:center;
                  gap:5px; }
.rb-admin       { background: linear-gradient(135deg, #fef3c7, #fde68a); 
                  color:#92400e; border:1px solid #fbbf24; }
.rb-premium     { background: linear-gradient(135deg, #fef9e3, #fef3c7); 
                  color:#b45309; border:1px solid #fbbf24; }
.rb-verified    { background: linear-gradient(135deg, #e0f2fe, #bae6fd); 
                  color:#0369a1; border:1px solid #38bdf8; }
.rb-user        { background: linear-gradient(135deg, #dcfce7, #bbf7d0); 
                  color:#166534; border:1px solid #4ade80; }

/* Premium Crown Animation */
@keyframes crownFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-5px) rotate(5deg); }
}
.premium-crown {
    display: inline-block;
    animation: crownFloat 2s ease-in-out infinite;
    font-size: 18px;
}

/* Verified Badge Animation */
@keyframes verifiedPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}
.verified-icon {
    display: inline-block;
    animation: verifiedPulse 2s ease-in-out infinite;
}

/* Premium Glow Effect */
.premium-glow {
    box-shadow: 0 0 20px rgba(255,215,0,0.3);
    border: 2px solid rgba(255,215,0,0.5);
    transition: all 0.3s ease;
}
.premium-glow:hover {
    box-shadow: 0 0 30px rgba(255,215,0,0.5);
    border-color: #ffd700;
}

/* Admin Glow */
.admin-glow {
    box-shadow: 0 0 20px rgba(220,53,69,0.3);
    border: 2px solid rgba(220,53,69,0.5);
}
.admin-glow:hover {
    box-shadow: 0 0 30px rgba(220,53,69,0.5);
}

.verified-badge-sm {
    background: #0ea5e9;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 8px;
}
</style>

<div class="container pb-5">

    <!-- Hero banner -->
    <div class="profile-hero <?= $isPremium ? 'premium-glow' : ($isAdmin ? 'admin-glow' : '') ?>">
        <div class="d-flex flex-column flex-md-row align-items-center gap-4">
            <div class="position-relative">
                <img src="<?= $imgUrl ?>" class="p-avatar">
                <?php if ($isPremium): ?>
                    <div style="position: absolute; bottom: 5px; right: 5px; background: #ffd700; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                        <i class="bi bi-star-fill" style="color: #000; font-size: 14px;"></i>
                    </div>
                <?php elseif ($isVerified): ?>
                    <div style="position: absolute; bottom: 5px; right: 5px; background: #0ea5e9; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border: 2px solid white;">
                        <i class="bi bi-patch-check-fill" style="color: white; font-size: 14px;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h3 class="fw-bold mb-0">
                        <?= sanitize($pu['name']) ?>
                        <?php if ($isPremium): ?>
                            <span class="premium-crown">👑</span>
                        <?php elseif ($isVerified): ?>
                            
                        <?php endif; ?>
                    </h3>
                    <?= getRoleBadgeHTML($pu['role']) ?>
                </div>
                <p class="mb-2" style="color:#cbd5e1;font-size:15px">
                    <?= sanitize($pu['job_title'] ?: 'BSCS Alumni') ?>
                    <?php if ($pu['company']): ?>
                        <span style="color:#94a3b8"> @ <?= sanitize($pu['company']) ?></span>
                    <?php endif; ?>
                </p>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    <?php if ($pu['degree']): ?>
                        <span class="info-pill"><i class="bi bi-mortarboard me-1"></i><?= sanitize($pu['degree']) ?></span>
                    <?php endif; ?>
                    <?php if ($pu['batch']): ?>
                        <span class="info-pill"><i class="bi bi-calendar me-1"></i>Batch <?= sanitize($pu['batch']) ?></span>
                    <?php endif; ?>
                    <?php if ($pu['session']): ?>
                        <span class="info-pill"><i class="bi bi-clock me-1"></i><?= sanitize($pu['session']) ?></span>
                    <?php endif; ?>
                    <?php if ($isPremium): ?>
                        <span class="info-pill" style="background: rgba(255,215,0,0.2); color: #ffd700;">
                            <i class="bi bi-star-fill me-1"></i>Premium Member
                        </span>
                    <?php elseif ($isVerified): ?>
                        <span class="info-pill" style="background: rgba(14,165,233,0.2); color: #38bdf8;">
                            <i class="bi bi-patch-check-fill me-1"></i>Verified Member
                        </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if ($isOwn): ?>
                        <a href="edit-profile.php" class="btn btn-danger btn-sm fw-semibold">
                            <i class="bi bi-pencil me-1"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                    <?php if (!$isOwn && isLoggedIn()): ?>
                        <a href="chat/room.php" class="btn btn-danger btn-sm fw-semibold">
                            <i class="bi bi-chat-dots me-1"></i> Group Chat
                        </a>
                    <?php endif; ?>
                    <?php if ($pu['linkedin_url']): ?>
                        <a href="<?= sanitize($pu['linkedin_url']) ?>" target="_blank"
                           class="btn btn-sm fw-semibold"
                           style="background:#0077b5;color:#fff">
                            <i class="bi bi-linkedin me-1"></i> LinkedIn
                        </a>
                    <?php endif; ?>
                    <a href="alumni-directory.php" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left me-1"></i> Directory
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- Left column -->
        <div class="col-lg-4">

            <!-- Stats -->
            <div class="card section-card">
                <div class="section-head">
                    <i class="bi bi-bar-chart text-danger me-2"></i>Activity
                </div>
                <div class="section-body">
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="stat-box">
                                <div class="stat-val"><?= $postCount ?></div>
                                <div class="stat-lbl">Posts</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box">
                                <div class="stat-val"><?= $likeCount ?></div>
                                <div class="stat-lbl">Likes</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="stat-box">
                                <div class="stat-val"><?= $commentCount ?></div>
                                <div class="stat-lbl">Comments</div>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-clock me-1"></i>
                            Member for <?= $memberSince ?> &bull;
                            Joined <?= date('M Y', strtotime($pu['created_at'])) ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="card section-card">
                <div class="section-head">
                    <i class="bi bi-person-lines-fill text-danger me-2"></i>Contact
                </div>
                <div class="section-body">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="text-muted" style="font-size:11px">Email</div>
                            <a href="mailto:<?= sanitize($pu['email']) ?>"
                               class="text-dark fw-semibold small">
                                <?= sanitize($pu['email']) ?>
                            </a>
                        </div>
                        <?php if ($pu['phone']): ?>
                        <div>
                            <div class="text-muted" style="font-size:11px">Phone</div>
                            <a href="tel:<?= sanitize($pu['phone']) ?>"
                               class="text-dark fw-semibold small">
                                <i class="bi bi-telephone me-1 text-danger"></i>
                                <?= sanitize($pu['phone']) ?>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if ($pu['linkedin_url']): ?>
                        <div>
                            <div class="text-muted" style="font-size:11px">LinkedIn</div>
                            <a href="<?= sanitize($pu['linkedin_url']) ?>" target="_blank"
                               class="text-dark fw-semibold small">
                                <i class="bi bi-linkedin me-1" style="color:#0077b5"></i>
                                View Profile
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$isOwn && isLoggedIn()): ?>
                        <div class="alert alert-light py-2 mt-3 mb-0 small border" style="border-radius:8px">
                            <i class="bi bi-shield-check text-success me-1"></i>
                            Visible to alumni only
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Skills -->
            <?php if (!empty($skills)): ?>
            <div class="card section-card">
                <div class="section-head">
                    <i class="bi bi-tags text-danger me-2"></i>Skills
                </div>
                <div class="section-body">
                    <?php foreach ($skills as $s): ?>
                        <span class="skill-chip"><?= sanitize($s) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Role Information Card -->
            <div class="card section-card">
                <div class="section-head">
                    <i class="bi bi-shield-check text-danger me-2"></i>Member Status
                </div>
                <div class="section-body">
                    <div class="d-flex flex-column gap-2">
                        <?php if ($isAdmin): ?>
                            <div class="info-box" style="border-left-color: #f59e0b;">
                                <div class="label">Administrator</div>
                                <div class="val">👑 Full platform access and management</div>
                            </div>
                        <?php elseif ($isPremium): ?>
                            <div class="info-box" style="border-left-color: #ffd700;">
                                <div class="label">Premium Member</div>
                                <div class="val"> Access to exclusive premium features</div>
                            </div>
                        <?php elseif ($isVerified): ?>
                            <div class="info-box" style="border-left-color: #0ea5e9;">
                                <div class="label">Verified Member</div>
                                <div class="val"> Verified alumni status</div>
                            </div>
                        <?php else: ?>
                            <div class="info-box" style="border-left-color: #9ca3af;">
                                <div class="label">Regular Member</div>
                                <div class="val">👤 Standard alumni member</div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($isPremium && !$isOwn): ?>
                            <div class="alert alert-warning py-2 mb-0 small" style="border-radius:8px; background: linear-gradient(135deg, #fef9e3, #fef3c7);">
                                <i class="bi bi-star-fill me-1" style="color: #f59e0b;"></i>
                                This user is a Premium Member! 
                            </div>
                        <?php elseif ($isVerified && !$isOwn): ?>
                            <div class="alert alert-info py-2 mb-0 small" style="border-radius:8px;">
                                <i class="bi bi-patch-check-fill me-1"></i>
                                This is a verified alumni member.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Right column -->
        <div class="col-lg-8">

            <!-- Bio -->
            <div class="card section-card">
                <div class="section-head">
                    <i class="bi bi-person-badge text-danger me-2"></i>
                    About <?= sanitize(explode(' ', $pu['name'])[0]) ?>
                </div>
                <div class="section-body">
                    <?php if ($pu['bio']): ?>
                        <p class="mb-0" style="font-size:14px;line-height:1.75;color:#374151">
                            <?= nl2br(sanitize($pu['bio'])) ?>
                        </p>
                    <?php else: ?>
                        <p class="text-muted small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            No bio added yet.
                            <?php if ($isOwn): ?>
                                <a href="edit-profile.php" class="text-danger">Add one →</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Education + Professional -->
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="card section-card h-100 mb-0">
                        <div class="section-head">
                            <i class="bi bi-mortarboard text-danger me-2"></i>Education
                        </div>
                        <div class="section-body">
                            <?php if ($pu['degree'] || $pu['batch'] || $pu['session']): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($pu['degree']): ?>
                                    <div class="info-box">
                                        <div class="label">Degree</div>
                                        <div class="val"><?= sanitize($pu['degree']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="row g-2">
                                        <?php if ($pu['session']): ?>
                                        <div class="col-6">
                                            <div class="info-box">
                                                <div class="label">Session</div>
                                                <div class="val"><?= sanitize($pu['session']) ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($pu['batch']): ?>
                                        <div class="col-6">
                                            <div class="info-box">
                                                <div class="label">Batch</div>
                                                <div class="val"><?= sanitize($pu['batch']) ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">
                                    No education info yet.
                                    <?php if ($isOwn): ?>
                                        <a href="edit-profile.php" class="text-danger">Add →</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card section-card h-100 mb-0">
                        <div class="section-head">
                            <i class="bi bi-briefcase text-danger me-2"></i>Professional
                        </div>
                        <div class="section-body">
                            <?php if ($pu['job_title'] || $pu['company']): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php if ($pu['job_title']): ?>
                                    <div class="info-box">
                                        <div class="label">Job Title</div>
                                        <div class="val"><?= sanitize($pu['job_title']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($pu['company']): ?>
                                    <div class="info-box">
                                        <div class="label">Company</div>
                                        <div class="val"><?= sanitize($pu['company']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">
                                    No professional info yet.
                                    <?php if ($isOwn): ?>
                                        <a href="edit-profile.php" class="text-danger">Add →</a>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent posts -->
            <?php if (!empty($recentPosts)): ?>
            <div class="card section-card">
                <div class="section-head d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-newspaper text-danger me-2"></i>Recent Posts</span>
                    <a href="posts/feed.php" class="text-danger small fw-semibold">View All →</a>
                </div>
                <div class="section-body">
                    <?php foreach ($recentPosts as $post): ?>
                    <div class="post-mini d-flex gap-3 align-items-start">
                        <?php if ($post['image'] && file_exists('uploads/posts/' . $post['image'])): ?>
                            <img src="uploads/posts/<?= $post['image'] ?>" class="post-mini-img">
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <p class="mb-1 small" style="color:#374151;line-height:1.5">
                                <?= substr(sanitize($post['content']), 0, 100) ?>
                                <?= strlen($post['content']) > 100 ? '...' : '' ?>
                            </p>
                            <div class="d-flex gap-3 text-muted" style="font-size:12px">
                                <span><i class="bi bi-heart-fill text-danger me-1"></i><?= $post['likes'] ?></span>
                                <span><i class="bi bi-chat me-1"></i><?= $post['comments'] ?></span>
                                <span><i class="bi bi-clock me-1"></i><?= date('M j, Y', strtotime($post['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>