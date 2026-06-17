<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= SITE_NAME ?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #e94560;
            --accent-dark: #c0303d;
            --nav-bg: #0d0d1a;
            --nav-border: rgba(233, 69, 96, 0.18);
            --nav-height: 64px;
            --text-muted: #8888aa;
            --text-bright: #f0f0ff;
        }

        body {
            background: #f0f2f7;
            font-family: 'Outfit', 'Segoe UI', sans-serif;
        }

        /* ── Navbar shell ── */
        .navbar {
            background: var(--nav-bg) !important;
            border-bottom: 1px solid var(--nav-border);
            min-height: var(--nav-height);
            padding: 0;
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .navbar .container {
            gap: 0;
        }

        /* ── Brand ── */
        .navbar-brand {
            color: var(--text-bright) !important;
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 0;
            margin-right: 32px;
            transition: opacity .2s;
        }

        .navbar-brand:hover { opacity: .85; }

        .brand-icon {
            width: 34px;
            height: 34px;
            background: linear-gradient(135deg, var(--accent), #ff7b54);
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            color: #fff;
            flex-shrink: 0;
        }

        /* ── Nav links ── */
        .navbar-nav .nav-item { margin: 0 1px; }

        .navbar-nav .nav-link {
            color: var(--text-muted) !important;
            font-size: 0.88rem;
            font-weight: 500;
            letter-spacing: 0.1px;
            padding: 6px 13px !important;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color .18s, background .18s;
            white-space: nowrap;
        }

        .navbar-nav .nav-link i { font-size: 15px; }

        .navbar-nav .nav-link:hover {
            color: var(--text-bright) !important;
            background: rgba(255,255,255,0.06);
        }

        .navbar-nav .nav-link.active {
            color: #fff !important;
            background: rgba(233, 69, 96, 0.18);
        }

        /* Admin link */
        .nav-link.admin-link {
            color: #f5c842 !important;
        }
        .nav-link.admin-link:hover {
            background: rgba(245, 200, 66, 0.1) !important;
        }

        /* ── Divider pill between nav and user area ── */
        .nav-divider {
            width: 1px;
            height: 22px;
            background: rgba(255,255,255,0.1);
            margin: 0 10px;
            align-self: center;
            flex-shrink: 0;
        }

        /* ── User dropdown trigger ── */
        .user-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 10px 5px 6px !important;
            border-radius: 40px;
            border: 1px solid rgba(255,255,255,0.08);
            transition: background .2s, border-color .2s;
            cursor: pointer;
        }

        .user-trigger:hover {
            background: rgba(255,255,255,0.07) !important;
            border-color: rgba(255,255,255,0.18);
        }

        .user-trigger::after { display: none; } /* hide default caret */

        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent);
            flex-shrink: 0;
        }

        .avatar-lg {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--accent);
        }

        .user-name {
            color: var(--text-bright) !important;
            font-size: 0.875rem;
            font-weight: 500;
            max-width: 120px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-caret {
            color: var(--text-muted);
            font-size: 12px;
            transition: transform .2s;
        }

        .user-trigger[aria-expanded="true"] .user-caret {
            transform: rotate(180deg);
        }

        /* ── Dropdown menu ── */
        .dropdown-menu {
            background: #13131f;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 6px;
            min-width: 190px;
            box-shadow: 0 16px 40px rgba(0,0,0,0.5);
            margin-top: 8px !important;
        }

        .dropdown-menu .dropdown-header {
            color: var(--text-muted);
            font-size: 0.72rem;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 6px 10px 4px;
        }

        .dropdown-item {
            color: #cccce0;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 9px;
            transition: background .15s, color .15s;
        }

        .dropdown-item i { font-size: 15px; color: var(--text-muted); }

        .dropdown-item:hover {
            background: rgba(255,255,255,0.07);
            color: #fff;
        }

        .dropdown-item:hover i { color: var(--accent); }

        .dropdown-item.text-danger { color: #f07878 !important; }
        .dropdown-item.text-danger i { color: #f07878 !important; }
        .dropdown-item.text-danger:hover {
            background: rgba(240,100,100,0.12);
            color: #ff6b6b !important;
        }

        .dropdown-divider {
            border-color: rgba(255,255,255,0.07);
            margin: 4px 0;
        }

        /* ── Auth buttons (guest) ── */
        .btn-nav-login {
            color: var(--text-muted) !important;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 6px 14px !important;
            border-radius: 8px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all .18s;
        }

        .btn-nav-login:hover {
            color: #fff !important;
            border-color: rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.06);
        }

        .btn-nav-register {
            background: var(--accent);
            color: #fff !important;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 6px 18px !important;
            border-radius: 8px;
            border: none;
            transition: background .18s, transform .15s;
        }

        .btn-nav-register:hover {
            background: var(--accent-dark);
            transform: translateY(-1px);
        }

        /* ── Toggler ── */
        .navbar-toggler {
            border-color: rgba(255,255,255,0.15) !important;
            padding: 5px 9px;
        }

        .navbar-toggler-icon {
            filter: invert(0.8);
        }

        /* ── General card & link reset ── */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        }

        .badge-role {
            background: var(--accent);
            color: #fff;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 20px;
        }

        a { text-decoration: none; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg mb-4">
    <div class="container">

        <!-- Brand → dashboard -->
        <a class="navbar-brand" href="<?= BASE_URL ?>/dashboard.php">
            <span class="brand-icon"><i class="bi bi-mortarboard-fill"></i></span>
            Alumni Connect
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#nav"
                aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-center">

                <?php if (isLoggedIn()): ?>

                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">
                            <i class="bi bi-house-door"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/alumni-directory.php">
                            <i class="bi bi-people"></i> Alumni
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/posts/feed.php">
                            <i class="bi bi-newspaper"></i> Feed
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= BASE_URL ?>/chat/room.php">
                            <i class="bi bi-chat-dots"></i> Chat
                        </a>
                    </li>

                    <?php if ($user['role'] === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link admin-link" href="<?= BASE_URL ?>/admin/dashboard.php">
                            <i class="bi bi-shield-check"></i> Admin
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Divider -->
                    <li class="d-none d-lg-flex nav-divider"></li>

                    <!-- User dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link user-trigger dropdown-toggle"
                           href="#" role="button" data-bs-toggle="dropdown"
                           aria-expanded="false">
                            <img src="<?= BASE_URL ?>/uploads/profiles/<?= $user['image'] ?>"
                                 class="avatar"
                                 onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=e94560&color=fff'">
                            <span class="user-name"><?= sanitize($user['name']) ?></span>
                            <i class="bi bi-chevron-down user-caret"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-header">My Account</span></li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/profile.php?id=<?= $user['id'] ?>">
                                    <i class="bi bi-person-circle"></i> My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= BASE_URL ?>/edit-profile.php">
                                    <i class="bi bi-pencil-square"></i> Edit Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/logout.php">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>

                    <li class="nav-item ms-1">
                        <a class="nav-link btn-nav-login" href="<?= BASE_URL ?>/login.php">
                            Login
                        </a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link btn-nav-register" href="<?= BASE_URL ?>/register.php">
                            Register
                        </a>
                    </li>

                <?php endif; ?>

            </ul>
        </div>
    </div>
</nav>
