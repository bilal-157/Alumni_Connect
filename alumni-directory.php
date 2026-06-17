<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

// Get page parameters
$page   = isset($_GET['page'])   ? (int)$_GET['page']          : 1;
$search = isset($_GET['search']) ? sanitize($_GET['search'])    : '';
$batch  = isset($_GET['batch'])  ? sanitize($_GET['batch'])     : '';
$degree = isset($_GET['degree']) ? sanitize($_GET['degree'])    : '';
$limit  = 12;
$offset = ($page - 1) * $limit;

// Build query - Exclude admins from directory
$whereConditions = ["u.role != 'admin'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(u.name LIKE ? OR u.email LIKE ? OR p.batch LIKE ? OR p.degree LIKE ? OR p.job_title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($batch)) {
    $whereConditions[] = "p.batch = ?";
    $params[] = $batch;
}

if (!empty($degree)) {
    $whereConditions[] = "p.degree = ?";
    $params[] = $degree;
}

$whereClause = implode(" AND ", $whereConditions);

// Total count
$countSql = "SELECT COUNT(*) as total FROM users u LEFT JOIN profiles p ON u.id = p.user_id WHERE $whereClause";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalUsers = $stmt->fetch()['total'];
$totalPages = ceil($totalUsers / $limit);

// Fetch users
$sql = "
    SELECT 
        u.id, u.name, u.email, u.role, u.created_at,
        COALESCE(p.batch, '')                   AS batch,
        COALESCE(p.session, '')                 AS session,
        COALESCE(p.degree, '')                  AS degree,
        COALESCE(p.company, '')                 AS company,
        COALESCE(p.job_title, '')               AS job_title,
        COALESCE(p.phone, '')                   AS phone,
        COALESCE(p.linkedin_url, '')            AS linkedin_url,
        COALESCE(p.bio, '')                     AS bio,
        COALESCE(p.profile_image, 'default.png') AS profile_image,
        COALESCE(p.skills, '')                  AS skills
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE $whereClause
    ORDER BY u.name ASC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Years & degrees
$currentYear    = date('Y');
$years          = array_reverse(range(1995, $currentYear));
$degreePrograms = [
    'BS Computer Science', 'BS Software Engineering', 'BS Information Technology',
    'BS English', 'BS Urdu', 'BS Psychology', 'BS Economics', 'BS Physics',
    'BS Chemistry', 'BS Mathematics', 'BS Biology', 'BBA', 'BCom',
    'BS Accounting', 'BS Marketing', 'BS Finance', 'LLB',
    'BS Political Science', 'BS Sociology', 'BS History',
    'BS International Relations', 'BS Mass Communication', 'BEd', 'BFA',
    'BArch', 'BS Civil Engineering', 'BS Electrical Engineering',
    'BS Mechanical Engineering', 'BS Chemical Engineering'
];

$existingBatchValues = array_column(
    $pdo->query("SELECT DISTINCT batch FROM profiles WHERE batch IS NOT NULL AND batch != '' ORDER BY batch DESC")->fetchAll(),
    'batch'
);
$existingDegreeValues = array_column(
    $pdo->query("SELECT DISTINCT degree FROM profiles WHERE degree IS NOT NULL AND degree != '' ORDER BY degree")->fetchAll(),
    'degree'
);

// Helper: resolve profile image
function getProfileImageSrc(string $filename): string {
    if (empty($filename) || $filename === 'default.png') return '';
    $abs = __DIR__ . '/uploads/profiles/' . $filename;
    return file_exists($abs) ? 'uploads/profiles/' . $filename : '';
}

// Avatar initials colour based on name
function avatarColor(string $name): string {
    $colors = ['#e63946','#2a9d8f','#e9c46a','#f4a261','#264653','#6a4c93','#1982c4','#8ac926'];
    return $colors[ord($name[0] ?? 'A') % count($colors)];
}

// Function to get role badge HTML
function getRoleBadge($role) {
    switch($role) {
        case 'premium':
            return '<span class="bd bd-premium"><i class="bi bi-star-fill"></i> Premium</span>';
        case 'verified':
            return '<span class="bd bd-verified"><i class="bi bi-patch-check-fill"></i> Verified</span>';
        case 'user':
            return '<span class="bd bd-member"><i class="bi bi-person-fill"></i> Alumni</span>';
        default:
            return '<span class="bd bd-member"><i class="bi bi-person-fill"></i> Member</span>';
    }
}
?>
<?php include 'includes/header.php'; ?>

<!-- ===== Google Fonts ===== -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
/* ── Root tokens ───────────────────────────────────────── */
:root {
    --red:      #dc3545;
    --red-dark: #b02a37;
    --red-soft: #fff0f1;
    --ink:      #1a1a2e;
    --ink-2:    #4a4a6a;
    --ink-3:    #8888aa;
    --surface:  #ffffff;
    --bg:       #f7f5f2;
    --border:   #e8e5e0;
    --radius:   16px;
    --shadow:   0 2px 12px rgba(26,26,46,.08);
    --shadow-lg:0 8px 32px rgba(26,26,46,.14);
    --gold:     #ffd700;
    --gold-glow: rgba(255,215,0,.15);
    --verified-blue: #0ea5e9;
    font-family: 'DM Sans', sans-serif;
}

/* ── Page shell ────────────────────────────────────────── */
.dir-page { background: var(--bg); min-height: 100vh; padding-bottom: 80px; }

/* ── Hero banner ───────────────────────────────────────── */
.dir-hero {
    background: var(--ink);
    background-image:
        radial-gradient(ellipse 60% 80% at 110% 50%, rgba(220,53,69,.35) 0%, transparent 65%),
        radial-gradient(ellipse 40% 60% at -10% 50%, rgba(106,76,147,.25) 0%, transparent 60%);
    color: #fff;
    padding: 64px 0 56px;
    position: relative;
    overflow: hidden;
}
.dir-hero::after {
    content: '';
    position: absolute; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.dir-hero h1 {
    font-family: 'DM Serif Display', serif;
    font-size: clamp(2.2rem, 5vw, 3.4rem);
    letter-spacing: -.02em;
    line-height: 1.1;
    margin: 0 0 10px;
}
.dir-hero h1 em { color: var(--red); font-style: italic; }
.dir-hero p { color: rgba(255,255,255,.6); font-size: .95rem; margin: 0; }
.stat-pill {
    display: inline-flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 100px;
    padding: 10px 22px;
    backdrop-filter: blur(8px);
}
.stat-pill .num {
    font-family: 'DM Serif Display', serif;
    font-size: 2rem;
    color: #fff;
    line-height: 1;
}
.stat-pill .lbl { font-size: .75rem; color: rgba(255,255,255,.55); text-transform: uppercase; letter-spacing: .08em; }

/* ── Filter card ───────────────────────────────────────── */
.filter-card {
    background: var(--surface);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 28px 28px 20px;
    margin-top: -28px;
    position: relative;
    z-index: 10;
    border: 1px solid var(--border);
}
.filter-card .form-label { font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--ink-2); margin-bottom: 6px; }
.filter-card .form-control,
.filter-card .form-select {
    border: 1.5px solid var(--border);
    border-radius: 10px;
    font-size: .9rem;
    color: var(--ink);
    padding: 9px 14px;
    transition: border-color .2s, box-shadow .2s;
}
.filter-card .form-control:focus,
.filter-card .form-select:focus {
    border-color: var(--red);
    box-shadow: 0 0 0 3px rgba(220,53,69,.1);
    outline: none;
}
.btn-apply {
    background: var(--red);
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 600;
    font-size: .88rem;
    padding: 10px 20px;
    transition: background .2s, transform .15s;
}
.btn-apply:hover { background: var(--red-dark); transform: translateY(-1px); color: #fff; }
.filter-tag {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--red-soft); color: var(--red);
    border: 1px solid rgba(220,53,69,.2);
    border-radius: 100px;
    font-size: .78rem; font-weight: 600;
    padding: 4px 14px 4px 12px;
    text-decoration: none;
    transition: background .2s;
}
.filter-tag:hover { background: #fde0e3; color: var(--red); }
.filter-tag i { font-size: .7rem; }

/* ── Results bar ───────────────────────────────────────── */
.results-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 4px;
    margin: 28px 0 16px;
}
.results-bar .count { font-size: .85rem; color: var(--ink-3); }
.results-bar .count strong { color: var(--ink); }

/* ── Alumni card ───────────────────────────────────────── */
.alumni-card {
    background: var(--surface);
    border-radius: var(--radius);
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
    transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s ease;
    height: 100%;
    display: flex; flex-direction: column;
}
.alumni-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-lg);
}
.alumni-card:hover .card-accent { width: 100%; }

.card-accent {
    height: 3px;
    background: linear-gradient(90deg, var(--red), #f4826a);
    width: 0;
    transition: width .35s ease;
}

/* Premium card glow */
.alumni-card.premium-card .card-accent {
    background: linear-gradient(90deg, var(--gold), #FFA500);
}
.alumni-card.premium-card:hover {
    border-color: var(--gold);
    box-shadow: 0 8px 32px rgba(255,215,0,.2);
}

/* Verified card glow */
.alumni-card.verified-card .card-accent {
    background: linear-gradient(90deg, var(--verified-blue), #38bdf8);
}
.alumni-card.verified-card:hover {
    border-color: var(--verified-blue);
    box-shadow: 0 8px 32px rgba(14,165,233,.2);
}

.card-body-inner {
    padding: 24px 20px 20px;
    display: flex; flex-direction: column; align-items: center;
    flex: 1;
}

/* avatar */
.av-wrap {
    position: relative;
    width: 88px; height: 88px;
    margin-bottom: 14px;
    flex-shrink: 0;
}
.av-wrap img,
.av-wrap .av-initials {
    width: 88px; height: 88px;
    border-radius: 50%;
    object-fit: cover;
}
.av-wrap .av-initials {
    display: flex; align-items: center; justify-content: center;
    font-family: 'DM Serif Display', serif;
    font-size: 1.7rem;
    color: #fff;
    letter-spacing: -.02em;
}
.av-ring {
    position: absolute; inset: -3px;
    border-radius: 50%;
    border: 2px solid transparent;
    background: linear-gradient(135deg, var(--red), #f4826a) border-box;
    -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
    -webkit-mask-composite: destination-out;
    mask-composite: exclude;
    transition: opacity .25s;
    opacity: 0;
}
.alumni-card:hover .av-ring { opacity: 1; }

/* Premium avatar ring */
.alumni-card.premium-card .av-ring {
    background: linear-gradient(135deg, var(--gold), #FFA500) border-box;
}
.alumni-card.verified-card .av-ring {
    background: linear-gradient(135deg, var(--verified-blue), #38bdf8) border-box;
}

.card-name {
    font-family: 'DM Serif Display', serif;
    font-size: 1.05rem;
    color: var(--ink);
    margin: 0 0 8px;
    text-align: center;
    line-height: 1.25;
}

/* badges */
.badge-row { display: flex; flex-wrap: wrap; gap: 4px; justify-content: center; margin-bottom: 12px; }
.bd {
    font-size: .68rem; font-weight: 600; letter-spacing: .04em;
    border-radius: 100px;
    padding: 2px 9px;
    line-height: 1.6;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.bd-alumni  { background: #fff0f1; color: var(--red); border: 1px solid rgba(220,53,69,.2); }
.bd-member  { background: #e8f4fd; color: #1565c0; border: 1px solid rgba(21,101,192,.2); }
.bd-premium { background: linear-gradient(135deg, #fef9e3, #fef3c7); color: #b45309; border: 1px solid var(--gold); }
.bd-verified { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #0369a1; border: 1px solid var(--verified-blue); }
.bd-batch   { background: #f1f0fb; color: #4a4a8a; border: 1px solid rgba(74,74,138,.2); }
.bd-degree  { background: #f0faf6; color: #1b7a50; border: 1px solid rgba(27,122,80,.2); }

/* meta */
.card-meta { width: 100%; }
.meta-row {
    display: flex; align-items: flex-start; gap: 7px;
    font-size: .8rem; color: var(--ink-2);
    margin-bottom: 5px;
    line-height: 1.4;
}
.meta-row i { color: var(--ink-3); flex-shrink: 0; margin-top: 1px; }

/* skills */
.skills-row { display: flex; flex-wrap: wrap; gap: 4px; margin: 10px 0 14px; width: 100%; }
.sk {
    font-size: .68rem; font-weight: 500;
    background: var(--bg); color: var(--ink-2);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 2px 8px;
}

/* view button */
.btn-view {
    display: block; width: 100%;
    margin-top: auto;
    background: transparent;
    border: 1.5px solid var(--border);
    border-radius: 10px;
    color: var(--ink-2);
    font-size: .82rem; font-weight: 600;
    padding: 8px;
    text-align: center;
    text-decoration: none;
    transition: background .2s, border-color .2s, color .2s;
}
.btn-view:hover {
    background: var(--red);
    border-color: var(--red);
    color: #fff;
}

/* Premium button hover */
.alumni-card.premium-card .btn-view:hover {
    background: var(--gold);
    border-color: var(--gold);
    color: #000;
}
.alumni-card.verified-card .btn-view:hover {
    background: var(--verified-blue);
    border-color: var(--verified-blue);
    color: #fff;
}

/* ── Empty state ───────────────────────────────────────── */
.empty-state {
    text-align: center;
    padding: 80px 24px;
    color: var(--ink-3);
}
.empty-state .icon { font-size: 3.5rem; opacity: .3; display: block; margin-bottom: 16px; }
.empty-state h4 { font-family: 'DM Serif Display', serif; color: var(--ink); margin-bottom: 8px; }
.empty-state p { font-size: .9rem; }

/* ── Pagination ────────────────────────────────────────── */
.pg { display: flex; justify-content: center; gap: 6px; margin-top: 48px; flex-wrap: wrap; }
.pg a, .pg span {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 38px; height: 38px;
    border-radius: 10px;
    font-size: .85rem; font-weight: 600;
    text-decoration: none;
    border: 1.5px solid var(--border);
    color: var(--ink-2);
    background: var(--surface);
    transition: all .18s;
}
.pg a:hover { border-color: var(--red); color: var(--red); }
.pg .active { background: var(--red); border-color: var(--red); color: #fff; }
.pg .dots { border-color: transparent; background: transparent; cursor: default; }

/* ── Animate in ────────────────────────────────────────── */
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.alumni-card {
    animation: fadeUp .4s ease both;
}
<?php foreach (range(1,12) as $i): ?>
.col-card:nth-child(<?= $i ?>) .alumni-card { animation-delay: <?= ($i-1) * 0.04 ?>s; }
<?php endforeach; ?>
</style>

<div class="dir-page">

    <!-- ── Hero ── -->
    <div class="dir-hero">
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-lg-7">
                    <h1><em>Alumni</em> Directory</h1>
                    <p>Connect with graduates and community members across every batch and discipline.</p>
                </div>
                <div class="col-lg-5 d-flex justify-content-lg-end">
                    <div class="stat-pill">
                        <div>
                            <div class="num"><?= number_format($totalUsers) ?></div>
                            <div class="lbl">Total Members</div>
                        </div>
                        <i class="bi bi-people-fill fs-3 text-white opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- ── Filters ── -->
        <div class="filter-card">
            <form method="GET" action="">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search Members</label>
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0" style="border:1.5px solid var(--border);border-right:none;border-radius:10px 0 0 10px;">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control border-start-0"
                                   style="border-radius:0 10px 10px 0;border-left:none;"
                                   placeholder="Name, degree, batch, job..."
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Batch Year</label>
                        <select name="batch" class="form-select">
                            <option value="">All Batches</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year ?>" <?= $batch == $year ? 'selected' : '' ?>>
                                    <?= $year ?><?= in_array($year, $existingBatchValues) ? ' ✓' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Degree Program</label>
                        <select name="degree" class="form-select">
                            <option value="">All Degrees</option>
                            <?php foreach ($degreePrograms as $program): ?>
                                <option value="<?= htmlspecialchars($program) ?>"
                                    <?= $degree == $program ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn-apply w-100">
                            <i class="bi bi-funnel-fill me-1"></i> Filter
                        </button>
                    </div>
                </div>
            </form>

            <?php if ($search || $batch || $degree): ?>
                <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                    <span style="font-size:.78rem;color:var(--ink-3);font-weight:600;text-transform:uppercase;letter-spacing:.06em;">Active:</span>
                    <?php if ($search): ?>
                        <a href="?<?= http_build_query(['batch'=>$batch,'degree'=>$degree]) ?>" class="filter-tag">
                            "<?= htmlspecialchars($search) ?>" <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($batch): ?>
                        <a href="?<?= http_build_query(['search'=>$search,'degree'=>$degree]) ?>" class="filter-tag">
                            Batch <?= htmlspecialchars($batch) ?> <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($degree): ?>
                        <a href="?<?= http_build_query(['search'=>$search,'batch'=>$batch]) ?>" class="filter-tag">
                            <?= htmlspecialchars($degree) ?> <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                    <a href="alumni-directory.php" class="filter-tag" style="background:#f5f5f5;color:var(--ink-3);border-color:var(--border);">
                        Clear all <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Results bar ── -->
        <?php if (!empty($users)): ?>
        <div class="results-bar">
            <p class="count mb-0">
                Showing <strong><?= count($users) ?></strong> of <strong><?= $totalUsers ?></strong> members
                <?php if ($search || $batch || $degree): ?>
                    <span style="color:var(--red);">— filtered</span>
                <?php endif; ?>
            </p>
            <?php if ($totalPages > 1): ?>
                <span class="count">Page <?= $page ?> / <?= $totalPages ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ── Grid ── -->
        <?php if (empty($users)): ?>
            <div class="empty-state">
                <i class="bi bi-people icon"></i>
                <h4>No Members Found</h4>
                <p>Try adjusting your filters or <a href="alumni-directory.php" style="color:var(--red);">clear all filters</a>.</p>
            </div>

        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($users as $user):
                    $imgSrc   = getProfileImageSrc($user['profile_image']);
                    $initials = strtoupper(substr($user['name'], 0, 1));
                    $skills   = array_slice(array_filter(array_map('trim', explode(',', $user['skills']))), 0, 3);
                    $roleClass = '';
                    if ($user['role'] === 'premium') $roleClass = 'premium-card';
                    if ($user['role'] === 'verified') $roleClass = 'verified-card';
                ?>
                <div class="col-md-6 col-lg-4 col-xl-3 col-card">
                    <div class="alumni-card <?= $roleClass ?>">
                        <div class="card-accent"></div>
                        <div class="card-body-inner">

                            <!-- Avatar -->
                            <div class="av-wrap">
                                <?php if ($imgSrc): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt="<?= htmlspecialchars($user['name']) ?>">
                                <?php else: ?>
                                    <div class="av-initials" style="background:<?= avatarColor($user['name']) ?>;">
                                        <?= $initials ?>
                                    </div>
                                <?php endif; ?>
                                <div class="av-ring"></div>
                            </div>

                            <!-- Name -->
                            <h5 class="card-name"><?= htmlspecialchars($user['name']) ?></h5>

                            <!-- Role Badges -->
                            <div class="badge-row">
                                <?= getRoleBadge($user['role']) ?>
                                <?php if ($user['batch']): ?>
                                    <span class="bd bd-batch"><?= htmlspecialchars($user['batch']) ?></span>
                                <?php endif; ?>
                                <?php if ($user['degree']): ?>
                                    <span class="bd bd-degree" title="<?= htmlspecialchars($user['degree']) ?>">
                                        <?= htmlspecialchars(strlen($user['degree']) > 22 ? substr($user['degree'],0,20).'…' : $user['degree']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Meta -->
                            <div class="card-meta">
                                <?php if ($user['job_title'] || $user['company']): ?>
                                    <div class="meta-row">
                                        <i class="bi bi-briefcase-fill"></i>
                                        <span>
                                            <?= htmlspecialchars($user['job_title']) ?>
                                            <?php if ($user['job_title'] && $user['company']): ?> · <?php endif; ?>
                                            <?php if ($user['company']): ?>
                                                <em style="font-style:normal;color:var(--ink);"><?= htmlspecialchars($user['company']) ?></em>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user['linkedin_url']): ?>
                                    <div class="meta-row">
                                        <i class="bi bi-linkedin"></i>
                                        <a href="<?= htmlspecialchars($user['linkedin_url']) ?>" target="_blank"
                                           style="color:var(--red);text-decoration:none;font-size:.78rem;">LinkedIn Profile</a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Skills -->
                            <?php if ($skills): ?>
                                <div class="skills-row">
                                    <?php foreach ($skills as $sk): ?>
                                        <span class="sk"><?= htmlspecialchars($sk) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- CTA -->
                            <a href="profile.php?id=<?= $user['id'] ?>" class="btn-view">
                                View Profile <i class="bi bi-arrow-right ms-1"></i>
                            </a>

                        </div><!-- /card-body-inner -->
                    </div><!-- /alumni-card -->
                </div>
                <?php endforeach; ?>
            </div><!-- /row -->

            <!-- ── Pagination ── -->
            <?php if ($totalPages > 1):
                $q = ['search'=>$search,'batch'=>$batch,'degree'=>$degree];
            ?>
            <div class="pg">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($q,['page'=>$page-1])) ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                if ($start > 1): ?>
                    <a href="?<?= http_build_query(array_merge($q,['page'=>1])) ?>">1</a>
                    <?php if ($start > 2): ?><span class="dots">…</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($q,['page'=>$i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><span class="dots">…</span><?php endif; ?>
                    <a href="?<?= http_build_query(array_merge($q,['page'=>$totalPages])) ?>"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= http_build_query(array_merge($q,['page'=>$page+1])) ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div><!-- /container -->
</div><!-- /dir-page -->

<?php include 'includes/footer.php'; ?>