<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $hashed]);
                $userId = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO profiles (user_id) VALUES (?)");
                $stmt->execute([$userId]);

                $pdo->commit();
                $success = 'Account created! You can now login.';
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Something went wrong. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join · Alumni Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #e94560;
            --primary-light: #ff6b85;
            --primary-glow: rgba(233, 69, 96, 0.4);
            --bg-dark: #0a0a12;
            --bg-card: rgba(15, 15, 28, 0.85);
            --bg-input: rgba(255, 255, 255, 0.03);
            --text-1: #f0f0ff;
            --text-2: #a0a0c0;
            --text-3: #606080;
            --border: rgba(255, 255, 255, 0.08);
            --radius: 16px;
            --radius-sm: 10px;
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ── FIX 1: allow scrolling, remove overflow:hidden ── */
        html {
            min-height: 100%;
        }

        body {
            font-family: 'Sora', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            /* FIX: padding-top + padding-bottom so card never clips at edges */
            padding: 40px 24px;
            position: relative;
            /* overflow:hidden REMOVED — this was blocking scroll */
            background: linear-gradient(135deg, #0a0a12 0%, #12122a 50%, #0a0a12 100%);
            /* keep background fixed so orbs don't scroll with content */
            background-attachment: fixed;
        }

        /* Orbs are fixed so they stay in place while page scrolls */
        body::before, body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: orbFloat 20s ease-in-out infinite;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            width: 600px; height: 600px;
            background: radial-gradient(circle, var(--primary), transparent 70%);
            top: -20%; right: -10%;
        }

        body::after {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #6b5ce7, transparent 70%);
            bottom: -15%; left: -10%;
            animation-delay: -10s;
        }

        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        .register-card {
            width: 100%;
            max-width: 460px;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 48px 40px;
            position: relative;
            z-index: 1;
            box-shadow: 
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(255, 255, 255, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            animation: cardEnter 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .brand-section {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeUp 0.6s 0.2s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .brand-orb {
            width: 72px; height: 72px;
            border-radius: 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 0 0 1px rgba(233, 69, 96, 0.3), 0 10px 40px rgba(233, 69, 96, 0.3);
            animation: orbPulse 3s ease-in-out infinite;
            position: relative;
        }

        .brand-orb::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 28px;
            border: 2px solid transparent;
            background: linear-gradient(135deg, var(--primary), transparent) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.3;
            animation: rotateBorder 8s linear infinite;
        }

        @keyframes orbPulse {
            0%, 100% { box-shadow: 0 0 0 1px rgba(233, 69, 96, 0.3), 0 10px 40px rgba(233, 69, 96, 0.3); }
            50% { box-shadow: 0 0 0 1px rgba(233, 69, 96, 0.5), 0 15px 50px rgba(233, 69, 96, 0.4); }
        }

        @keyframes rotateBorder { to { transform: rotate(360deg); } }

        .brand-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-1);
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }

        .brand-subtitle {
            font-size: 0.9rem;
            color: var(--text-2);
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 22px;
            animation: fadeUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }
        .form-group:nth-child(3) { animation-delay: 0.5s; }
        .form-group:nth-child(4) { animation-delay: 0.6s; }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-2);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
            transition: color 0.3s;
        }

        .form-group:focus-within .form-label { color: var(--primary-light); }

        .input-wrap { position: relative; }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 1rem;
            transition: var(--transition);
            pointer-events: none;
        }

        .form-group:focus-within .input-icon { color: var(--primary); }

        .form-control-custom {
            width: 100%;
            background: var(--bg-input);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 14px 16px 14px 48px;
            color: var(--text-1);
            font-size: 0.95rem;
            font-family: inherit;
            transition: var(--transition);
            outline: none;
        }

        .form-control-custom::placeholder { color: var(--text-3); font-size: 0.9rem; }

        .form-control-custom:hover {
            border-color: rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.04);
        }

        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(233, 69, 96, 0.1), 0 0 20px rgba(233, 69, 96, 0.05);
            background: rgba(255, 255, 255, 0.05);
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-3);
            cursor: pointer;
            font-size: 1rem;
            padding: 4px;
            transition: color 0.3s;
            z-index: 2;
        }

        .password-toggle:hover { color: var(--text-2); }

        .error-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-left: 3px solid #ef4444;
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: #fca5a5;
            animation: shake 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        .success-alert {
            background: rgba(46, 204, 138, 0.1);
            border: 1px solid rgba(46, 204, 138, 0.2);
            border-left: 3px solid #2ecc8a;
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 0.85rem;
            color: #6ee7b7;
            animation: fadeUp 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .success-alert a {
            color: #2ecc8a;
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid rgba(46, 204, 138, 0.3);
            transition: all 0.3s;
        }

        .success-alert a:hover {
            color: #6ee7b7;
            border-bottom-color: #6ee7b7;
        }

        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .btn-register {
            width: 100%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            border-radius: var(--radius-sm);
            padding: 16px;
            color: #fff;
            font-size: 0.95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            position: relative;
            transition: var(--transition);
            box-shadow: 0 8px 32px rgba(233, 69, 96, 0.3);
            animation: fadeUp 0.6s 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

.btn-register::before {
    display: none;
}

.btn-register:hover {
    box-shadow: 0 12px 40px rgba(233, 69, 96, 0.5), 0 0 60px rgba(233, 69, 96, 0.15);
}
        .btn-register:hover::before { left: 100%; }
        .btn-register:active { transform: translateY(0); }
        .btn-register i { font-size: 1.1rem; transition: transform 0.3s; }
        .btn-register:hover i { transform: translateX(3px); }

        /* ── FIX 2: btn-back now has enough contrast and is always visible ── */
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: var(--radius-sm);
            border: 1.5px solid rgba(255,255,255,0.15);
            background: rgba(255,255,255,0.04);
            color: var(--text-2);
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            animation: fadeUp 0.6s 0.85s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            /* ensure it's never invisible against dark bg */
            opacity: 1 !important;
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: rgba(233, 69, 96, 0.08);
        }

        .btn-back i { transition: transform 0.3s; }
        .btn-back:hover i { transform: translateX(-3px); }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            animation: fadeUp 0.6s 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
        }

        .divider span {
            font-size: 0.75rem;
            color: var(--text-3);
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .login-prompt {
            text-align: center;
            margin-top: 24px;
            animation: fadeUp 0.6s 0.9s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .login-prompt p { color: var(--text-3); font-size: 0.9rem; margin: 0; }

        .login-prompt a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            position: relative;
            transition: color 0.3s;
        }

        .login-prompt a::after {
            content: '';
            position: absolute;
            bottom: -2px; left: 0;
            width: 0; height: 2px;
            background: var(--primary);
            transition: width 0.3s;
            border-radius: 2px;
        }

        .login-prompt a:hover { color: var(--primary-light); }
        .login-prompt a:hover::after { width: 100%; }

        /* Particles fixed so they don't contribute to scroll height */
        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px; height: 4px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0;
            animation: particleFloat 15s ease-in-out infinite;
        }

        @keyframes particleFloat {
            0%   { opacity: 0; transform: translateY(100vh) scale(0); }
            10%  { opacity: 0.6; }
            90%  { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-10vh) scale(1); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            body { padding: 24px 16px; background-attachment: scroll; }
            body::before, body::after { display: none; }
            .register-card {
                padding: 32px 22px;
                border: 1px solid var(--border);
                background: rgba(15,15,28,0.95);
            }
            .brand-orb { width: 60px; height: 60px; border-radius: 20px; font-size: 26px; }
            .brand-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="particles" id="particles"></div>

<div class="register-card">
    
    <div class="brand-section">
        <div class="brand-orb">
            <i class="bi bi-person-plus-fill"></i>
        </div>
        <h1 class="brand-title">Create Account</h1>
        <p class="brand-subtitle">Join the BSCS Alumni Network</p>
    </div>

    <?php if ($error): ?>
        <div class="error-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= $success ?> <a href="<?= BASE_URL ?>/login.php">Login here &rarr;</a>
        </div>
    <?php endif; ?>

    <form method="POST" id="registerForm">
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <div class="input-wrap">
                <i class="bi bi-person input-icon"></i>
                <input type="text" name="name" class="form-control-custom"
                       placeholder="e.g. Ali Hassan"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                       required autocomplete="name">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <div class="input-wrap">
                <i class="bi bi-envelope input-icon"></i>
                <input type="email" name="email" class="form-control-custom"
                       placeholder="your@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autocomplete="email">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="password" id="passwordInput"
                       class="form-control-custom"
                       placeholder="Min. 6 characters"
                       required autocomplete="new-password">
                <button type="button" class="password-toggle" id="togglePassword" tabindex="-1">
                    <i class="bi bi-eye-slash" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="input-wrap">
                <i class="bi bi-shield-lock input-icon"></i>
                <input type="password" name="confirm_password" id="confirmInput"
                       class="form-control-custom"
                       placeholder="Repeat your password"
                       required autocomplete="new-password">
                <button type="button" class="password-toggle" id="toggleConfirm" tabindex="-1">
                    <i class="bi bi-eye-slash" id="toggleConfirmIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-register" id="submitBtn">
            <span>Create Account</span>
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <div class="divider"><span>or</span></div>

    <a href="<?= BASE_URL ?>/login.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>
        Back to Login
    </a>

    <div class="login-prompt">
        <p>Already have an account? <a href="<?= BASE_URL ?>/login.php">Sign in</a></p>
    </div>
</div>

<script>
    // Particles
    const particlesContainer = document.getElementById('particles');
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.left            = Math.random() * 100 + '%';
        p.style.animationDelay  = Math.random() * 15 + 's';
        p.style.animationDuration = (10 + Math.random() * 10) + 's';
        p.style.width = p.style.height = (2 + Math.random() * 4) + 'px';
        particlesContainer.appendChild(p);
    }

    // Password toggles
    function setupToggle(btnId, inputId, iconId) {
        document.getElementById(btnId).addEventListener('click', () => {
            const input = document.getElementById(inputId);
            const icon  = document.getElementById(iconId);
            const show  = input.type === 'password';
            input.type  = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye' : 'bi bi-eye-slash';
        });
    }

    setupToggle('togglePassword', 'passwordInput', 'toggleIcon');
    setupToggle('toggleConfirm',  'confirmInput',  'toggleConfirmIcon');

    // Disable button on submit
    document.getElementById('registerForm').addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite"></span><span>Creating...</span>';
        btn.style.opacity = '0.8';
    });

    // Mouse-follow glow on inputs
    document.querySelectorAll('.form-control-custom').forEach(input => {
        input.addEventListener('mousemove', e => {
            const r = input.getBoundingClientRect();
            input.style.background = `radial-gradient(circle 80px at ${e.clientX - r.left}px ${e.clientY - r.top}px, rgba(233,69,96,0.08), transparent), var(--bg-input)`;
        });
        input.addEventListener('mouseleave', () => { input.style.background = ''; });
    });
</script>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>
</html>