<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("
            SELECT u.*, p.profile_image
            FROM users u
            LEFT JOIN profiles p ON p.user_id = u.id
            WHERE u.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['user_image'] = $user['profile_image'] ?? 'default.png';

            header('Location: ' . BASE_URL . '/dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · Alumni Connect</title>
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

        html {
            min-height: 100%;
            overflow-x: hidden;
        }

        body {
            font-family: 'Sora', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            position: relative;
            overflow-x: hidden;
            overflow-y: auto;
            background: linear-gradient(135deg, #0a0a12 0%, #12122a 50%, #0a0a12 100%);
        }

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
            top: -20%; left: -10%;
        }

        body::after {
            width: 500px; height: 500px;
            background: radial-gradient(circle, #6b5ce7, transparent 70%);
            bottom: -15%; right: -10%;
            animation-delay: -10s;
        }

        @keyframes orbFloat {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
        }

        .login-card {
            width: 100%;
            max-width: 420px;
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
            flex-shrink: 0;
        }

        @keyframes cardEnter {
            from { opacity: 0; transform: translateY(40px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .brand-section {
            text-align: center;
            margin-bottom: 40px;
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
            margin-bottom: 24px;
            animation: fadeUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }
        .form-group:nth-child(2) { animation-delay: 0.4s; }

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

        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .error-alert i { font-size: 1.1rem; flex-shrink: 0; }

        .forgot-link {
            text-align: right;
            margin-bottom: 28px;
            animation: fadeUp 0.6s 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .forgot-link a {
            color: var(--text-3);
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            transition: color 0.3s;
            position: relative;
        }

        .forgot-link a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--primary);
            transition: width 0.3s;
        }

        .forgot-link a:hover { color: var(--primary-light); }
        .forgot-link a:hover::after { width: 100%; }

        .btn-login {
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
            overflow: hidden;
            transition: var(--transition);
            box-shadow: 0 8px 32px rgba(233, 69, 96, 0.3);
            animation: fadeUp 0.6s 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(233, 69, 96, 0.4);
        }

        .btn-login:hover::before { left: 100%; }
        .btn-login:active { transform: translateY(0); }

        .btn-login i {
            font-size: 1.1rem;
            transition: transform 0.3s;
        }

        .btn-login:hover i { transform: translateX(3px); }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 32px 0;
            animation: fadeUp 0.6s 0.7s cubic-bezier(0.34, 1.56, 0.64, 1) both;
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

        .btn-signup {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: transparent;
            color: var(--text-2);
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            animation: fadeUp 0.6s 0.75s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .btn-signup:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: rgba(233, 69, 96, 0.05);
        }

        .btn-signup i { transition: transform 0.3s; }
        .btn-signup:hover i { transform: translateX(3px); }

        .register-prompt {
            text-align: center;
            margin-top: 24px;
            animation: fadeUp 0.6s 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .register-prompt p {
            color: var(--text-3);
            font-size: 0.9rem;
            margin: 0;
        }

        .register-prompt a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            position: relative;
            transition: color 0.3s;
        }

        .register-prompt a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
            border-radius: 2px;
        }

        .register-prompt a:hover { color: var(--primary-light); }
        .register-prompt a:hover::after { width: 100%; }

        .particles {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0;
            animation: particleFloat 15s ease-in-out infinite;
        }

        @keyframes particleFloat {
            0% { opacity: 0; transform: translateY(100vh) scale(0); }
            10% { opacity: 0.6; }
            90% { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-10vh) scale(1); }
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 16px;
                background: var(--bg-dark);
            }
            
            body::before, body::after { display: none; }
            
            .login-card {
                padding: 32px 24px;
                border: none;
                box-shadow: none;
                background: var(--bg-dark);
            }
            
            .brand-orb {
                width: 60px;
                height: 60px;
                border-radius: 20px;
                font-size: 26px;
            }
            
            .brand-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

<div class="particles" id="particles"></div>

<div class="login-card">
    
    <div class="brand-section">
        <div class="brand-orb">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <h1 class="brand-title">Welcome Back</h1>
        <p class="brand-subtitle">Sign in to your alumni account</p>
    </div>

    <?php if ($error): ?>
        <div class="error-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="loginForm">
        <div class="form-group">
            <label class="form-label">Email Address</label>
            <div class="input-wrap">
                <i class="bi bi-envelope input-icon"></i>
                <input 
                    type="email" 
                    name="email" 
                    class="form-control-custom"
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                    required
                    autocomplete="email"
                >
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input 
                    type="password" 
                    name="password" 
                    id="passwordInput"
                    class="form-control-custom"
                    placeholder="Enter your password" 
                    required
                    autocomplete="current-password"
                >
                <button type="button" class="password-toggle" id="togglePassword" tabindex="-1">
                    <i class="bi bi-eye-slash" id="toggleIcon"></i>
                </button>
            </div>
        </div>

        <div class="forgot-link">
            <a href="<?= BASE_URL ?>/forgot-password.php">Forgot password?</a>
        </div>

        <button type="submit" class="btn-login" id="submitBtn">
            <span>Sign In</span>
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <div class="divider">
        <span>or</span>
    </div>

    <a href="<?= BASE_URL ?>/register.php" class="btn-signup">
        <i class="bi bi-person-plus"></i>
        Create New Account
    </a>

    <div class="register-prompt">
        <p>Don't have an account? <a href="<?= BASE_URL ?>/register.php">Sign up</a></p>
    </div>
</div>

<script>
    const particlesContainer = document.getElementById('particles');
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.left = Math.random() * 100 + '%';
        particle.style.animationDelay = Math.random() * 15 + 's';
        particle.style.animationDuration = (10 + Math.random() * 10) + 's';
        particle.style.width = (2 + Math.random() * 4) + 'px';
        particle.style.height = particle.style.width;
        particlesContainer.appendChild(particle);
    }

    const toggleBtn = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('passwordInput');
    const toggleIcon = document.getElementById('toggleIcon');

    toggleBtn.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        toggleIcon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
    });

    const form = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', () => {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;"></span><span>Signing in...</span>';
        submitBtn.style.opacity = '0.8';
    });

    document.querySelectorAll('.form-control-custom').forEach(input => {
        input.addEventListener('mousemove', (e) => {
            const rect = input.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            input.style.background = `radial-gradient(circle 80px at ${x}px ${y}px, rgba(233, 69, 96, 0.08), transparent), var(--bg-input)`;
        });
        
        input.addEventListener('mouseleave', () => {
            input.style.background = '';
        });
    });
</script>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>
</html>