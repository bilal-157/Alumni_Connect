<?php
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load email config
$mailConfig = require 'config/mail.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');

    if (!$email) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Delete any old tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            // Generate a secure token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)")
                ->execute([$user['id'], $token, $expiresAt]);

            $resetLink = BASE_URL . '/reset-password.php?token=' . $token;
            
            // ========== SEND EMAIL USING PHPMailer ==========
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = $mailConfig['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $mailConfig['username'];
                $mail->Password   = $mailConfig['password'];
                $mail->SMTPSecure = $mailConfig['encryption'] === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = $mailConfig['port'];
                
                // Disable SSL verification for local testing (remove in production)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                
                // Optional: Enable debug output (remove in production)
                // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                
                // Recipients
                $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
                $mail->addAddress($email, $user['name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - Alumni Connect';
                $mail->Body    = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                            .content { padding: 20px; background: #f8f9fa; }
                            .button { display: inline-block; padding: 12px 24px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
                            .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c757d; }
                            .link { word-break: break-all; color: #0066cc; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>Password Reset Request</h2>
                            </div>
                            <div class='content'>
                                <p>Hello <strong>{$user['name']}</strong>,</p>
                                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                                <p style='text-align: center;'>
                                    <a href='{$resetLink}' class='button'>Reset Password</a>
                                </p>
                                <p>Or copy this link to your browser:</p>
                                <p><a href='{$resetLink}' class='link'>{$resetLink}</a></p>
                                <p><strong>This link will expire in 1 hour.</strong></p>
                                <p>If you didn't request this, please ignore this email.</p>
                                <hr>
                                <p style='font-size: 12px; color: #6c757d;'>This is an automated message, please do not reply.</p>
                            </div>
                            <div class='footer'>
                                &copy; " . date('Y') . " Alumni Connect. All rights reserved.
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $mail->AltBody = "Hello {$user['name']},\n\nWe received a request to reset your password.\n\nClick this link to reset your password: {$resetLink}\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
                
                $mail->send();
                $success = 'Password reset link has been sent to your email address! Please check your inbox (and spam folder).';
                
            } catch (Exception $e) {
                $error = "Failed to send email. Please try again later.";
                // Log error for debugging
                error_log("Mail Error: " . $mail->ErrorInfo);
            }
            
        } else {
            // Security: Don't reveal if email exists
            $success = 'If that email exists in our system, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password · Alumni Connect</title>
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

        body {
            font-family: 'Sora', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #0a0a12 0%, #12122a 50%, #0a0a12 100%);
        }

        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.15;
            animation: orbFloat 20s ease-in-out infinite;
            pointer-events: none;
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

        .reset-card {
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
            margin-bottom: 24px;
            animation: fadeUp 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .form-group:nth-child(1) { animation-delay: 0.3s; }

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

        .success-alert i { font-size: 1.1rem; flex-shrink: 0; }

        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-4px); }
            40%, 60% { transform: translateX(4px); }
        }

        .btn-reset {
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
            animation: fadeUp 0.6s 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 40px rgba(233, 69, 96, 0.4);
        }

        .btn-reset:hover::before { left: 100%; }
        .btn-reset:active { transform: translateY(0); }

        .btn-reset i {
            font-size: 1.1rem;
            transition: transform 0.3s;
        }

        .btn-reset:hover i { transform: translateX(3px); }

        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px;
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
            animation: fadeUp 0.6s 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary-light);
            background: rgba(233, 69, 96, 0.05);
        }

        .btn-back i { transition: transform 0.3s; }
        .btn-back:hover i { transform: translateX(-3px); }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            animation: fadeUp 0.6s 0.45s cubic-bezier(0.34, 1.56, 0.64, 1) both;
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
            animation: fadeUp 0.6s 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        .login-prompt p {
            color: var(--text-3);
            font-size: 0.9rem;
            margin: 0;
        }

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
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
            border-radius: 2px;
        }

        .login-prompt a:hover { color: var(--primary-light); }
        .login-prompt a:hover::after { width: 100%; }

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
                padding: 16px;
                background: var(--bg-dark);
            }
            
            body::before, body::after { display: none; }
            
            .reset-card {
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

<div class="reset-card">
    
    <div class="brand-section">
        <div class="brand-orb">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <h1 class="brand-title">Forgot Password?</h1>
        <p class="brand-subtitle">No worries, we'll send you a reset link</p>
    </div>

    <?php if ($error): ?>
        <div class="error-alert">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-alert">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($success) ?>
        </div>
        
        <a href="<?= BASE_URL ?>/login.php" class="btn-back">
            <i class="bi bi-arrow-left"></i>
            Return to Login
        </a>

        <div class="login-prompt" style="margin-top: 24px;">
            <p>Didn't receive it? <a href="<?= BASE_URL ?>/forgot-password.php">Try again</a></p>
        </div>

    <?php else: ?>

    <form method="POST" id="resetForm">
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

        <button type="submit" class="btn-reset" id="submitBtn">
            <span>Send Reset Link</span>
            <i class="bi bi-send-fill"></i>
        </button>
    </form>

    <div class="divider">
        <span>or</span>
    </div>

    <a href="<?= BASE_URL ?>/login.php" class="btn-back">
        <i class="bi bi-arrow-left"></i>
        Back to Login
    </a>

    <?php endif; ?>
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

    const form = document.getElementById('resetForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form) {
        form.addEventListener('submit', () => {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;"></span><span>Sending...</span>';
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
    }
</script>

<style>
    @keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>
</html>