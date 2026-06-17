<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
requireLogin();

$user = currentUser();

$totalPosts  = $pdo->query("SELECT COUNT(*) FROM posts")->fetchColumn();
$totalAlumni = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT u.*, p.* FROM users u
    LEFT JOIN profiles p ON p.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$user['id']]);
$myProfile = $stmt->fetch();

// Get ONLY premium users for carousel
$premiumStmt = $pdo->query("
    SELECT
        u.id, u.name, u.role,
        COALESCE(p.batch, '')                    AS batch,
        COALESCE(p.degree, '')                   AS degree,
        COALESCE(p.company, '')                  AS company,
        COALESCE(p.job_title, '')                AS job_title,
        COALESCE(p.skills, '')                   AS skills,
        COALESCE(p.profile_image, 'default.png') AS profile_image
    FROM users u
    LEFT JOIN profiles p ON u.id = p.user_id
    WHERE u.role = 'premium'
    ORDER BY u.created_at DESC
    LIMIT 30
");
$premiumUsers = $premiumStmt->fetchAll();

// Get regular alumni for stats (non-admin, non-premium)
$regularAlumni = $pdo->query("
    SELECT COUNT(*) FROM users 
    WHERE role != 'admin' AND role != 'premium'
")->fetchColumn();

$premiumCount = count($premiumUsers);

function profileSrc(string $filename, string $name): string {
    $filename = $filename ?: 'default.png';
    if ($filename !== 'default.png') {
        if (file_exists(__DIR__ . '/uploads/' . $filename))
            return 'uploads/' . htmlspecialchars($filename);
        if (file_exists(__DIR__ . '/uploads/profiles/' . $filename))
            return 'uploads/profiles/' . htmlspecialchars($filename);
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=dc3545&color=fff&size=100&bold=true';
}

function avatarColor(string $name): string {
    $colors = ['#e63946','#1982c4','#2a9d8f','#f4a261','#6a4c93','#e9c46a','#264653','#8ac926'];
    return $colors[ord($name[0] ?? 'A') % count($colors)];
}

$myAvatarSrc = profileSrc($myProfile['profile_image'] ?? '', $user['name']);
$firstName   = explode(' ', $user['name'])[0];
$myInitials  = strtoupper(substr($user['name'], 0, 1));

// Check user role for welcome badge
$userRole = $user['role'];
$isPremium = ($userRole === 'premium');
$isVerified = ($userRole === 'verified');
$isAdmin = ($userRole === 'admin');

// Get role badge text and icon
function getWelcomeBadge($role) {
    switch($role) {
        case 'admin':
            return ['icon' => 'bi bi-shield-lock-fill', 'text' => ' Administrator', 'color' => '#f5c842'];
        case 'premium':
            return ['icon' => 'bi bi-star-fill', 'text' => ' Premium Member', 'color' => '#ffd700'];
        case 'verified':
            return ['icon' => 'bi bi-patch-check-fill', 'text' => '✅ Verified Member', 'color' => '#0ea5e9'];
        default:
            return ['icon' => 'bi bi-person-fill', 'text' => '🎓 Alumni Member', 'color' => '#22c55e'];
    }
}

$welcomeBadge = getWelcomeBadge($userRole);
?>
<?php include 'includes/header.php'; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --primary:     #dc3545;
    --primary-dk:  #b91c1c;
    --primary-glow:rgba(220,53,69,.15);
    --ink:         #0f0f14;
    --ink-2:       #3d3d52;
    --ink-3:       #9090a8;
    --surface:     #ffffff;
    --bg:          #f5f4f1;
    --border:      rgba(0,0,0,.08);
    --border-2:    rgba(0,0,0,.14);
    --radius:      16px;
    --radius-sm:   12px;
    --gold:        #ffd700;
    --gold-glow:   rgba(255,215,0,.15);
    font-family: 'Inter', sans-serif;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body { background: var(--bg); min-height: 100vh; }
.dash-wrap { padding: 28px 0 60px; }

/* ── Animations ── */
@keyframes slideUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); }
    50%       { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
}
@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-6px); }
}
@keyframes glow {
    0%, 100% { box-shadow: 0 0 15px rgba(220,53,69,.2); }
    50% { box-shadow: 0 0 35px rgba(220,53,69,.4); }
}
@keyframes goldGlow {
    0%, 100% { box-shadow: 0 0 15px rgba(255,215,0,.3); }
    50% { box-shadow: 0 0 35px rgba(255,215,0,.6); }
}
@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
@keyframes crownFloat {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-8px) rotate(5deg); }
}

.anim { animation: slideUp .5s ease both; }
.anim-1 { animation-delay: .05s; }
.anim-2 { animation-delay: .10s; }
.anim-3 { animation-delay: .15s; }
.anim-4 { animation-delay: .20s; }
.anim-5 { animation-delay: .25s; }
.anim-6 { animation-delay: .30s; }

/* ── Top Bar ── */
.top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    padding: 0 4px;
    animation: slideUp .4s ease both;
}
.top-bar h1 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 22px;
    font-weight: 700;
    color: var(--ink);
    letter-spacing: -.5px;
}
.top-bar h1 em {
    background: linear-gradient(135deg, var(--primary), #f97316);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-style: normal;
}
.top-bar-sub {
    font-size: 12px;
    color: var(--ink-3);
    margin-top: 4px;
    font-weight: 400;
}
.top-bar-right {
    display: flex;
    align-items: center;
    gap: 12px;
}
.online-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: 100px;
    padding: 7px 16px;
    font-size: 12px;
    font-weight: 600;
    color: var(--ink-3);
    box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #22c55e;
    animation: pulse 2s infinite;
}
.top-avatar-wrap {
    position: relative;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
}
.top-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--surface);
    box-shadow: 0 2px 12px rgba(0,0,0,.15);
    transition: transform .3s, box-shadow .3s;
    display: block;
}
.top-avatar-initials {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), #f97316);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid var(--surface);
    box-shadow: 0 2px 12px rgba(0,0,0,.15);
    transition: transform .3s, box-shadow .3s;
}
.top-avatar:hover, .top-avatar-initials:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 20px rgba(220,53,69,.3);
}

/* ── Greeting Bar ── */
.greet-bar {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    background-size: 200% 200%;
    animation: gradientShift 8s ease infinite;
    border-radius: var(--radius);
    padding: 28px 32px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 32px rgba(15,15,20,.2);
}
.greet-bar::before {
    content: '';
    position: absolute;
    right: -60px; top: -60px;
    width: 240px; height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(220,53,69,.25) 0%, transparent 70%);
    pointer-events: none;
    animation: float 6s ease-in-out infinite;
}
.greet-bar::after {
    content: '';
    position: absolute;
    left: -40px; bottom: -40px;
    width: 180px; height: 180px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(249,115,22,.15) 0%, transparent 70%);
    pointer-events: none;
    animation: float 8s ease-in-out infinite reverse;
}
.greet-text h3 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 6px;
    position: relative; z-index: 1;
}
.greet-text h3 span {
    background: linear-gradient(135deg, #fb7185, #fbbf24);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.greet-text p {
    font-size: 13px;
    color: rgba(255,255,255,.5);
    font-weight: 400;
    position: relative; z-index: 1;
}
.greet-badge {
    background: <?= $isPremium ? 'linear-gradient(135deg, #FFD700, #FFA500)' : ($isVerified ? 'linear-gradient(135deg, #0ea5e9, #3b82f6)' : ($isAdmin ? 'linear-gradient(135deg, #f59e0b, #fbbf24)' : 'rgba(255,255,255,.1)')) ?>;
    backdrop-filter: blur(10px);
    border: 1px solid <?= $isPremium ? 'rgba(255,215,0,.5)' : ($isVerified ? 'rgba(14,165,233,.5)' : ($isAdmin ? 'rgba(245,158,11,.5)' : 'rgba(255,255,255,.2)')) ?>;
    border-radius: 100px;
    padding: 8px 20px;
    font-size: 12px;
    font-weight: 600;
    color: <?= ($isPremium || $isVerified || $isAdmin) ? '#000' : 'rgba(255,255,255,.8)' ?>;
    white-space: nowrap;
    flex-shrink: 0;
    position: relative; z-index: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    animation: <?= $isPremium ? 'goldGlow 3s ease-in-out infinite' : 'glow 3s ease-in-out infinite' ?>;
}

/* ── Quick Stats ── */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 28px;
}
.qs-box {
    background: var(--surface);
    border: 1px solid var(--border-2);
    border-radius: var(--radius-sm);
    padding: 20px 14px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    color: inherit;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    position: relative;
    overflow: hidden;
}
.qs-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), #f97316);
    transform: scaleX(0);
    transition: transform .3s;
}
.qs-box:hover::before { transform: scaleX(1); }
.qs-box:hover {
    border-color: var(--primary);
    background: linear-gradient(180deg, var(--surface) 0%, var(--primary-glow) 100%);
    transform: translateY(-4px);
    box-shadow: 0 12px 30px rgba(220,53,69,.12);
}
.qs-box i {
    font-size: 24px;
    color: var(--primary);
    transition: transform .3s, color .3s;
}
.qs-box:hover i {
    transform: scale(1.2) rotate(-5deg);
    color: var(--primary-dk);
}
.qs-num {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.qs-lbl {
    font-size: 10px;
    font-weight: 600;
    color: var(--ink-3);
    text-transform: uppercase;
    letter-spacing: .08em;
}

/* ── Section Header ── */
.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.section-header h2 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: var(--ink);
}
.section-header h2 em {
    background: linear-gradient(135deg, var(--gold), #FFA500);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    font-style: normal;
}
.section-badge {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--gold);
    background: var(--gold-glow);
    border: 1px solid var(--gold);
    border-radius: 100px;
    padding: 5px 14px;
}

/* ── Premium Carousel Styling ── */
.carousel-wrap {
    margin-bottom: 0;
    position: relative;
}
.carousel-wrap::before {
    content: '';
    position: absolute;
    top: -20px; left: -20px; right: -20px;
    height: 60px;
    background: linear-gradient(180deg, var(--gold-glow) 0%, transparent 100%);
    border-radius: 50%;
    pointer-events: none;
    opacity: 0.5;
}
.carousel-outer {
    overflow: hidden;
    border-radius: var(--radius);
    box-shadow: 0 8px 40px rgba(0,0,0,.1);
}
.carousel-track {
    display: flex;
    transition: transform .7s cubic-bezier(.25,.46,.45,.94);
    will-change: transform;
}
.carousel-slide {
    flex: 0 0 100%;
    padding: 0 2px;
}

/* Premium Card - Enhanced */
.impact-card {
    background: var(--surface);
    border: 2px solid transparent;
    border-radius: var(--radius);
    display: flex;
    overflow: hidden;
    transition: all .4s cubic-bezier(.34,1.56,.64,1);
    text-decoration: none;
    min-height: 320px;
    position: relative;
}
.impact-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,215,0,.05) 0%, rgba(255,165,0,.05) 100%);
    opacity: 0;
    transition: opacity .4s;
    pointer-events: none;
}
.impact-card:hover::before { opacity: 1; }
.impact-card:hover {
    border-color: var(--gold);
    box-shadow: 0 20px 60px rgba(255,215,0,.15), 0 0 0 1px rgba(255,215,0,.1);
    transform: translateY(-4px);
}

.card-strip {
    width: 6px;
    background: linear-gradient(180deg, var(--gold), #FFA500, var(--gold));
    background-size: 100% 200%;
    animation: shimmer 3s linear infinite;
    flex-shrink: 0;
}

/* Premium Crown */
.premium-crown {
    position: absolute;
    top: -12px;
    right: 20px;
    font-size: 32px;
    animation: crownFloat 2s ease-in-out infinite;
    z-index: 10;
    filter: drop-shadow(0 4px 8px rgba(0,0,0,.2));
}

.card-left {
    padding: 36px 28px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    flex: 0 0 260px;
    border-right: 1px solid var(--border);
    background: linear-gradient(180deg, var(--surface) 0%, #fafafa 100%);
    position: relative;
}
.card-left::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 80px;
    background: linear-gradient(180deg, transparent 0%, rgba(255,215,0,.03) 100%);
    pointer-events: none;
}

.card-right {
    padding: 36px 32px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    flex: 1;
    gap: 0;
    position: relative;
}

/* Avatar with premium ring */
.av-wrap {
    position: relative;
    width: 110px;
    height: 110px;
    margin-bottom: 18px;
}
.av-img,
.av-initials {
    width: 110px;
    height: 110px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid var(--surface);
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    display: block;
    transition: all .4s cubic-bezier(.34,1.56,.64,1);
    position: relative;
    z-index: 2;
}
.av-initials {
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 36px;
    font-weight: 700;
    color: #fff;
    border: none;
    text-shadow: 0 2px 10px rgba(0,0,0,.2);
}
.impact-card:hover .av-img,
.impact-card:hover .av-initials {
    transform: scale(1.08);
    box-shadow: 0 8px 30px rgba(255,215,0,.25);
}
.av-ring {
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    border: 3px solid var(--gold);
    opacity: 0;
    transition: all .4s cubic-bezier(.34,1.56,.64,1);
    transform: scale(.85);
    animation: rotate 10s linear infinite;
    border-style: dashed;
}
.impact-card:hover .av-ring {
    opacity: 1;
    transform: scale(1);
}
.av-status {
    position: absolute;
    bottom: 6px;
    right: 6px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), #FFA500);
    border: 3px solid var(--surface);
    animation: pulse 2.5s infinite;
    z-index: 3;
}

.impact-name {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 18px;
    font-weight: 700;
    color: var(--ink);
    margin-bottom: 5px;
    line-height: 1.3;
}
.impact-title {
    font-size: 13px;
    color: var(--ink-3);
    font-weight: 500;
    margin-bottom: 14px;
}
.badge-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
}
.ibadge {
    font-size: 11px;
    font-weight: 600;
    border-radius: 100px;
    padding: 4px 12px;
    line-height: 1.6;
    transition: all .2s;
}
.ibadge:hover { transform: translateY(-2px); }
.ib-premium {
    background: linear-gradient(135deg, #fff0e6, #ffe6cc);
    color: #cc7000;
    border: 1px solid rgba(255,215,0,.5);
    box-shadow: 0 2px 8px rgba(255,215,0,.2);
}
.ib-alumni {
    background: linear-gradient(135deg, #fff0f1, #ffe4e6);
    color: #be123c;
    border: 1px solid rgba(220,53,69,.3);
}
.ib-batch {
    background: linear-gradient(135deg, #eef2ff, #e0e7ff);
    color: #4338ca;
    border: 1px solid rgba(67,56,202,.3);
}
.ib-degree {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    color: #047857;
    border: 1px solid rgba(4,120,87,.3);
}

/* Detail Grid */
.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 18px;
}
.detail-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    background: linear-gradient(135deg, #fafafa, #f5f5f5);
    border-radius: var(--radius-sm);
    font-size: 13px;
    color: var(--ink-2);
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    border: 1px solid transparent;
}
.detail-item:hover {
    background: linear-gradient(135deg, #fff8f0, #fff5ea);
    border-color: rgba(255,215,0,.3);
    transform: translateX(5px) translateY(-2px);
    box-shadow: 0 4px 12px rgba(255,215,0,.1);
}
.detail-item i {
    font-size: 18px;
    color: var(--gold);
    flex-shrink: 0;
    margin-top: 1px;
    transition: transform .3s;
}
.detail-item:hover i { transform: scale(1.2); color: #FFA500; }
.detail-item strong {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: var(--ink);
    text-transform: uppercase;
    letter-spacing: .06em;
    margin-bottom: 3px;
}

/* Skills */
.skills-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 20px;
}
.skill-tag {
    font-size: 12px;
    font-weight: 600;
    background: linear-gradient(135deg, #f3f4f6, #e5e7eb);
    color: var(--ink-2);
    border: 1px solid var(--border-2);
    border-radius: 10px;
    padding: 5px 12px;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    cursor: default;
}
.skill-tag:hover {
    background: linear-gradient(135deg, var(--gold), #FFA500);
    color: #000;
    border-color: var(--gold);
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 4px 15px rgba(255,215,0,.5);
}
.impact-card:hover .skill-tag {
    background: linear-gradient(135deg, #fff8f0, #fff5ea);
    border-color: rgba(255,215,0,.3);
    color: #cc7000;
}
.impact-card:hover .skill-tag:hover {
    background: linear-gradient(135deg, var(--gold), #FFA500);
    color: #000;
}

/* CTA - Gold Gradient */
.impact-cta {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: linear-gradient(135deg, var(--gold), #FFA500);
    background-size: 200% 200%;
    animation: gradientShift 4s ease infinite;
    border-radius: 12px;
    color: #000;
    font-size: 13px;
    font-weight: 700;
    padding: 12px 28px;
    text-decoration: none;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    align-self: flex-start;
    box-shadow: 0 4px 20px rgba(255,215,0,.4);
    border: none;
    position: relative;
    overflow: hidden;
}
.impact-cta::before {
    content: '';
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,.5), transparent);
    transition: left .5s;
}
.impact-cta:hover::before { left: 100%; }
.impact-cta:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 30px rgba(255,215,0,.6);
}
.impact-cta i { transition: transform .3s; }
.impact-cta:hover i { transform: translateX(4px); }

/* Carousel Nav */
.carousel-nav {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 20px;
    margin-top: 24px;
    margin-bottom: 28px;
}
.carousel-arrow {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid var(--border-2);
    background: var(--surface);
    color: var(--ink-2);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 18px;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    box-shadow: 0 4px 15px rgba(0,0,0,.08);
    position: relative;
    overflow: hidden;
}
.carousel-arrow::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--gold), #FFA500);
    opacity: 0;
    transition: opacity .3s;
    border-radius: 50%;
}
.carousel-arrow:hover::before { opacity: 1; }
.carousel-arrow:hover {
    border-color: var(--gold);
    color: #000;
    transform: scale(1.15);
    box-shadow: 0 8px 25px rgba(255,215,0,.4);
}
.carousel-arrow:hover i { position: relative; z-index: 1; }
.carousel-arrow:active { transform: scale(.95); }
.carousel-dots {
    display: flex;
    gap: 10px;
    align-items: center;
}
.carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--border-2);
    cursor: pointer;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    border: none;
    padding: 0;
    position: relative;
}
.carousel-dot::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    border: 2px solid transparent;
    transition: all .3s;
}
.carousel-dot:hover {
    background: var(--ink-3);
    transform: scale(1.3);
}
.carousel-dot.active {
    background: var(--gold);
    transform: scale(1.5);
    box-shadow: 0 0 15px rgba(255,215,0,.6);
}
.carousel-dot.active::after { border-color: rgba(255,215,0,.5); }
.carousel-counter {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 13px;
    color: var(--gold);
    font-weight: 700;
    min-width: 50px;
    text-align: center;
    background: var(--gold-glow);
    padding: 6px 12px;
    border-radius: 100px;
    border: 1px solid var(--gold);
}

/* Empty State */
.empty-premium {
    background: linear-gradient(135deg, var(--surface), #fafafa);
    border-radius: var(--radius);
    padding: 60px 20px;
    text-align: center;
    border: 2px dashed var(--border-2);
}
.empty-premium i {
    font-size: 60px;
    color: var(--gold);
    margin-bottom: 20px;
    opacity: 0.5;
}
.empty-premium h3 {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 20px;
    color: var(--ink-2);
    margin-bottom: 10px;
}
.empty-premium p {
    color: var(--ink-3);
    font-size: 14px;
}

/* View All */
.view-all-wrap { text-align: center; margin-bottom: 32px; }
.view-all-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--ink-3);
    text-decoration: none;
    border: 2px solid var(--border-2);
    border-radius: 100px;
    padding: 12px 28px;
    background: var(--surface);
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    position: relative;
    overflow: hidden;
}
.view-all-link::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--primary), #f97316);
    opacity: 0;
    transition: opacity .3s;
    border-radius: 100px;
}
.view-all-link:hover::before { opacity: 1; }
.view-all-link:hover {
    border-color: var(--primary);
    color: #fff;
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(220,53,69,.25);
}
.view-all-link:hover i,
.view-all-link:hover span { position: relative; z-index: 1; }
.view-all-link i { transition: transform .3s; }
.view-all-link:hover i { transform: translateX(4px); }

/* Bottom Stats */
.bottom-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
.bs-box {
    background: linear-gradient(135deg, var(--surface) 0%, #fafafa 100%);
    border: 1px solid var(--border-2);
    border-radius: var(--radius);
    padding: 28px 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    transition: all .3s cubic-bezier(.34,1.56,.64,1);
    position: relative;
    overflow: hidden;
}
.bs-box::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--primary), #f97316);
    transform: scaleX(0);
    transition: transform .4s;
}
.bs-box:hover::before { transform: scaleX(1); }
.bs-box:hover {
    border-color: var(--primary);
    transform: translateY(-5px);
    box-shadow: 0 16px 40px rgba(220,53,69,.12);
}
.bs-box i {
    font-size: 28px;
    color: var(--primary);
    transition: all .3s;
}
.bs-box:hover i {
    transform: scale(1.2) rotate(-8deg);
    color: var(--primary-dk);
}
.bs-num {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 28px;
    font-weight: 700;
    color: var(--ink);
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.bs-lbl {
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-3);
    text-transform: uppercase;
    letter-spacing: .08em;
}

/* Responsive */
@media (max-width: 991px) {
    .impact-card   { flex-direction: column; min-height: auto; }
    .card-strip    { width: 100%; height: 6px; }
    .card-left     { flex: none; padding: 28px 24px; border-right: none; border-bottom: 1px solid var(--border); }
    .card-right    { padding: 28px 24px; }
    .detail-grid   { grid-template-columns: 1fr; }
    .quick-stats   { grid-template-columns: repeat(2, 1fr); }
    .bottom-stats  { grid-template-columns: 1fr; }
    .av-wrap, .av-img, .av-initials { width: 90px; height: 90px; }
    .av-initials   { font-size: 28px; }
    .premium-crown { font-size: 28px; top: -8px; right: 15px; }
}
@media (max-width: 576px) {
    .top-bar       { flex-direction: column; gap: 14px; text-align: center; }
    .top-bar h1    { font-size: 20px; }
    .greet-bar     { flex-direction: column; text-align: center; padding: 24px; }
    .greet-text h3 { font-size: 17px; }
    .quick-stats   { grid-template-columns: repeat(2, 1fr); }
    .carousel-nav  { gap: 12px; }
    .carousel-arrow{ width: 40px; height: 40px; font-size: 15px; }
    .carousel-dot  { width: 8px; height: 8px; }
    .impact-name   { font-size: 16px; }
    .impact-cta    { padding: 10px 22px; font-size: 12px; }
    .premium-crown { font-size: 24px; top: -6px; right: 12px; }
}
</style>

<div class="dash-wrap">
<div class="container">

    <!-- ── Top Bar ── -->
    <div class="top-bar anim">
        <div>
            <h1>Alumni <em>Dashboard</em></h1>
            <div class="top-bar-sub"><?= date('l, F j, Y') ?> · Stay connected with your community</div>
        </div>
        <div class="top-bar-right">
            <div class="online-pill">
                <span class="pulse-dot"></span>
                Online
            </div>
            <a href="profile.php?id=<?= $user['id'] ?>" class="top-avatar-wrap">
                <?php if ($myAvatarSrc && strpos($myAvatarSrc, 'ui-avatars') === false): ?>
                    <img src="<?= htmlspecialchars($myAvatarSrc) ?>" class="top-avatar" alt="<?= htmlspecialchars($user['name']) ?>">
                <?php else: ?>
                    <div class="top-avatar-initials"><?= $myInitials ?></div>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- ── Greeting Bar with Member Status ── -->
    <div class="greet-bar anim anim-1">
        <div class="greet-text">
            <h3>Welcome back, <span><?= htmlspecialchars($firstName) ?></span> 👋</h3>
            <p>Explore your alumni network and stay updated with community news</p>
        </div>
        <div class="greet-badge">
            <i class="<?= $welcomeBadge['icon'] ?>" style="color: <?= $welcomeBadge['color'] ?>; font-size:14px;"></i>
            <?= $welcomeBadge['text'] ?>
        </div>
    </div>

    <!-- ── Quick Stats ── -->
    <div class="quick-stats">
        <a href="alumni-directory.php" class="qs-box anim anim-2">
            <i class="bi bi-people-fill"></i>
            <span class="qs-num"><?= number_format($totalAlumni) ?></span>
            <span class="qs-lbl">Total Alumni</span>
        </a>
        <a href="posts/feed.php" class="qs-box anim anim-3">
            <i class="bi bi-newspaper"></i>
            <span class="qs-num"><?= number_format($totalPosts) ?></span>
            <span class="qs-lbl">Posts</span>
        </a>
        <a href="premium-upgrade.php" class="qs-box anim anim-4">
            <i class="bi bi-star-fill"></i>
            <span class="qs-num"><?= $premiumCount ?></span>
            <span class="qs-lbl">Premium Members</span>
        </a>
        <a href="profile.php?id=<?= $user['id'] ?>" class="qs-box anim anim-5">
            <i class="bi bi-person-fill"></i>
            <span class="qs-num">Me</span>
            <span class="qs-lbl">Profile</span>
        </a>
    </div>

    <!-- ── Section Header ── -->
    <div class="section-header anim anim-5">
        <h2>Premium <em>Members</em></h2>
        <span class="section-badge"> <?= $premiumCount ?> premium alumni</span>
    </div>

    <!-- ── Premium Carousel ── -->
    <div class="carousel-wrap anim anim-6">
        <?php if ($premiumCount > 0): ?>
        <div class="carousel-outer">
            <div class="carousel-track" id="alumniCarouselTrack">

                <?php foreach ($premiumUsers as $al):
                    $imgSrc   = profileSrc($al['profile_image'] ?? '', $al['name']);
                    $initials = strtoupper(substr($al['name'], 0, 1));
                    $skills   = array_slice(array_filter(array_map('trim', explode(',', $al['skills']))), 0, 6);
                    $color    = avatarColor($al['name']);
                    $isImg    = $imgSrc && strpos($imgSrc, 'ui-avatars') === false;
                ?>
                <div class="carousel-slide">
                    <a href="profile.php?id=<?= $al['id'] ?>" class="impact-card">
                        <div class="card-strip"></div>
                        

                        <!-- Left -->
                        <div class="card-left">
                            <div class="av-wrap">
                                <?php if ($isImg): ?>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="av-img" alt="<?= htmlspecialchars($al['name']) ?>">
                                <?php else: ?>
                                    <div class="av-initials" style="background:<?= $color ?>;"><?= $initials ?></div>
                                <?php endif; ?>
                                <div class="av-ring"></div>
                                <div class="av-status"></div>
                            </div>

                            <div class="impact-name"><?= htmlspecialchars($al['name']) ?></div>
                            <?php if ($al['job_title']): ?>
                                <div class="impact-title"><?= htmlspecialchars($al['job_title']) ?></div>
                            <?php endif; ?>

                            <div class="badge-row">
                                <span class="ibadge ib-premium"> Premium Member</span>
                                <?php if ($al['batch']): ?>
                                    <span class="ibadge ib-batch"><?= htmlspecialchars($al['batch']) ?></span>
                                <?php endif; ?>
                                <?php if ($al['degree']): ?>
                                    <span class="ibadge ib-degree"><?= htmlspecialchars(strlen($al['degree']) > 18 ? substr($al['degree'],0,15).'…' : $al['degree']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Right -->
                        <div class="card-right">
                            <div class="detail-grid">
                                <?php if ($al['company']): ?>
                                <div class="detail-item">
                                    <i class="bi bi-building-fill"></i>
                                    <div><strong>Company</strong><?= htmlspecialchars($al['company']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($al['batch']): ?>
                                <div class="detail-item">
                                    <i class="bi bi-mortarboard-fill"></i>
                                    <div><strong>Batch</strong><?= htmlspecialchars($al['batch']) ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($al['degree']): ?>
                                <div class="detail-item">
                                    <i class="bi bi-award-fill"></i>
                                    <div><strong>Degree</strong><?= htmlspecialchars(strlen($al['degree']) > 28 ? substr($al['degree'],0,25).'…' : $al['degree']) ?></div>
                                </div>
                                <?php endif; ?>
                                <div class="detail-item">
                                    <i class="bi bi-shield-fill-check"></i>
                                    <div><strong>Status</strong>Premium Member</div>
                                </div>
                                <div class="detail-item">
                                    <i class="bi bi-envelope-fill"></i>
                                    <div><strong>Contact</strong>Available</div>
                                </div>
                                <div class="detail-item">
                                    <i class="bi bi-geo-alt-fill"></i>
                                    <div><strong>Network</strong>Global</div>
                                </div>
                            </div>

                            <?php if ($skills): ?>
                            <div class="skills-row">
                                <?php foreach ($skills as $sk): ?>
                                    <span class="skill-tag"><?= htmlspecialchars($sk) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <span class="impact-cta">
                                View Profile <i class="bi bi-arrow-right"></i>
                            </span>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>

            </div><!-- /track -->
        </div><!-- /outer -->

        <!-- Navigation -->
        <div class="carousel-nav">
            <button class="carousel-arrow" onclick="Carousel.prev()" aria-label="Previous">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div class="carousel-dots" id="carouselDots"></div>
            <span class="carousel-counter" id="carouselCounter">1 / <?= $premiumCount ?></span>
            <button class="carousel-arrow" onclick="Carousel.next()" aria-label="Next">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-premium">
            <i class="bi bi-star"></i>
            <h3>No Premium Members Yet</h3>
            <p>Premium members will be featured here once they join.</p>
            <?php if (!$isPremium): ?>
            <a href="premium-upgrade.php" class="btn btn-warning mt-3">
                <i class="bi bi-star-fill"></i> Become a Premium Member
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- View All -->
    <div class="view-all-wrap">
        <a href="alumni-directory.php" class="view-all-link">
            <span>View All Alumni</span> <i class="bi bi-arrow-right"></i>
        </a>
    </div>

    <!-- ── Bottom Stats ── -->
    <div class="bottom-stats">
        <div class="bs-box anim anim-4">
            <i class="bi bi-people-fill"></i>
            <span class="bs-num"><?= number_format($totalAlumni) ?></span>
            <span class="bs-lbl">Total Alumni</span>
        </div>
        <div class="bs-box anim anim-5">
            <i class="bi bi-star-fill"></i>
            <span class="bs-num"><?= $premiumCount ?></span>
            <span class="bs-lbl">Premium Members</span>
        </div>
        <div class="bs-box anim anim-6">
            <i class="bi bi-building"></i>
            <span class="bs-num">Est. 1908</span>
            <span class="bs-lbl">Since</span>
        </div>
    </div>

</div><!-- /container -->
</div>

<script>
const Carousel = {
    track:    null,
    dotsWrap: null,
    counter:  null,
    slides:   [],
    total:    0,
    current:  0,
    timer:    null,
    delay:    5000,
    touchX:   0,

    init() {
        this.track    = document.getElementById('alumniCarouselTrack');
        if (!this.track) return;
        this.dotsWrap = document.getElementById('carouselDots');
        this.counter  = document.getElementById('carouselCounter');
        this.slides   = this.track.querySelectorAll('.carousel-slide');
        this.total    = this.slides.length;
        if (!this.total) return;
        this.buildDots();
        this.update();
        this.startAuto();
        this.bindEvents();
    },

    buildDots() {
        if (!this.dotsWrap) return;
        this.dotsWrap.innerHTML = '';
        for (let i = 0; i < this.total; i++) {
            const d = document.createElement('button');
            d.className = 'carousel-dot' + (i === 0 ? ' active' : '');
            d.setAttribute('aria-label', 'Premium Member ' + (i + 1));
            d.addEventListener('click', () => { this.goTo(i); this.resetAuto(); });
            this.dotsWrap.appendChild(d);
        }
    },

    goTo(i) {
        this.current = Math.max(0, Math.min(i, this.total - 1));
        this.update();
    },

    next() { this.current = (this.current + 1) % this.total; this.update(); },
    prev() { this.current = (this.current - 1 + this.total) % this.total; this.update(); },

    update() {
        if (this.track) this.track.style.transform = `translateX(-${this.current * 100}%)`;
        if (this.dotsWrap) {
            this.dotsWrap.querySelectorAll('.carousel-dot').forEach((d, i) =>
                d.classList.toggle('active', i === this.current));
        }
        if (this.counter)
            this.counter.textContent = `${this.current + 1} / ${this.total}`;
    },

    startAuto() { 
        if (this.total > 1) {
            this.timer = setInterval(() => this.next(), this.delay);
        }
    },
    resetAuto()  { clearInterval(this.timer); this.startAuto(); },

    bindEvents() {
        if (!this.track) return;
        const outer = this.track.parentElement;
        if (outer) {
            outer.addEventListener('touchstart', e => { this.touchX = e.changedTouches[0].screenX; }, { passive: true });
            outer.addEventListener('touchend',   e => {
                const diff = this.touchX - e.changedTouches[0].screenX;
                if (Math.abs(diff) > 45) { diff > 0 ? this.next() : this.prev(); this.resetAuto(); }
            }, { passive: true });
            outer.addEventListener('mouseenter', () => clearInterval(this.timer));
            outer.addEventListener('mouseleave', () => this.startAuto());
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'ArrowLeft')  { this.prev(); this.resetAuto(); }
            if (e.key === 'ArrowRight') { this.next(); this.resetAuto(); }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => Carousel.init());
</script>

<?php include 'includes/footer.php'; ?>