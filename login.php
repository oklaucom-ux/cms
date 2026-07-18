<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['login_id'])) {
    header("Location: dashboard.php");
    exit();
}

$companyName = 'Cyno Management';
$companyLogo = '';
try {
    foreach($pdo->query("SELECT * FROM settings") as $row) {
        if($row['setting_key'] === 'company_name') $companyName = $row['setting_value'];
        if($row['setting_key'] === 'company_logo') $companyLogo = $row['setting_value'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= htmlspecialchars($companyName) ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700&display=swap" rel="stylesheet">
    
    <link rel="manifest" href="manifest.json">
    <style>
        :root {
            --glass-bg: rgba(15, 23, 42, 0.65);
            --glass-border: rgba(255, 255, 255, 0.1);
            --neon-blue: #0ea5e9;
            --neon-cyan: #22d3ee;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #f8fafc;
            overflow: hidden;
            position: relative;
        }

        /* Animated Tech Background */
        .tech-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 0;
            background: 
                radial-gradient(circle at 15% 50%, rgba(14, 165, 233, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 85% 30%, rgba(99, 102, 241, 0.15) 0%, transparent 50%);
            animation: pulse-bg 15s ease-in-out infinite alternate;
        }

        .tech-grid {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 1;
            background-image: 
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            perspective: 1000px;
            transform-style: preserve-3d;
            animation: grid-move 20s linear infinite;
        }

        @keyframes pulse-bg {
            0% { transform: scale(1); }
            100% { transform: scale(1.1); }
        }

        @keyframes grid-move {
            0% { background-position: 0 0; }
            100% { background-position: 50px 50px; }
        }

        /* Glassmorphism Container */
        .login-glass-card {
            position: relative;
            z-index: 10;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255,255,255,0.05) inset;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideUpFade {
            to { transform: translateY(0); opacity: 1; }
        }

        /* Header Logo & Typography */
        .login-logo { 
            width: auto; 
            max-width: 160px; 
            height: auto; 
            max-height: 70px; 
            display: block; 
            margin: 0 auto 1.5rem; 
            filter: drop-shadow(0 0 10px rgba(255,255,255,0.2));
        }
        
        .login-logo-placeholder { 
            width: 56px; 
            height: 56px; 
            border-radius: 16px; 
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-cyan)); 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 28px; 
            margin: 0 auto 1.5rem; 
            color: white; 
            box-shadow: 0 0 20px rgba(14, 165, 233, 0.4);
        }

        .company-title {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
            letter-spacing: 1px;
            background: linear-gradient(to right, #f8fafc, #94a3b8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.25rem;
        }

        .login-subtitle { 
            font-size: 0.9rem; 
            color: #94a3b8; 
            text-align: center;
            margin-bottom: 2rem; 
        }

        /* Form Inputs */
        .form-floating {
            margin-bottom: 1.25rem;
        }

        .form-control {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #f8fafc;
            border-radius: 12px;
            padding-left: 3.5rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: rgba(0, 0, 0, 0.3);
            border-color: var(--neon-cyan);
            color: #f8fafc;
            box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.1);
        }
        
        .form-control::placeholder {
            color: transparent; /* hidden for floating label */
        }

        .form-floating label {
            color: #64748b;
            padding-left: 3.5rem;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 1.25rem;
            transform: translateY(-50%);
            color: #64748b;
            z-index: 5;
            transition: color 0.3s ease;
        }

        .form-control:focus ~ .input-icon,
        .form-control:not(:placeholder-shown) ~ .input-icon {
            color: var(--neon-cyan);
        }

        /* Button */
        .btn-login {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-cyan));
            border: none;
            border-radius: 12px;
            color: #0f172a;
            font-weight: 700;
            font-size: 1rem;
            padding: 0.8rem;
            width: 100%;
            margin-top: 0.5rem;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(14, 165, 233, 0.4);
            color: #0f172a;
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
        }

        .btn-login:hover::after {
            left: 150%;
        }

        /* Alerts */
        .login-error { 
            background: rgba(239, 68, 68, 0.1); 
            border: 1px solid rgba(239, 68, 68, 0.3); 
            color: #fca5a5; 
            border-radius: 12px; 
            padding: 12px 16px; 
            font-size: 0.85rem; 
            margin-top: 1.5rem; 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .login-success { 
            background: rgba(34, 197, 94, 0.1); 
            border: 1px solid rgba(34, 197, 94, 0.3); 
            color: #86efac; 
            border-radius: 12px; 
            padding: 12px 16px; 
            font-size: 0.85rem; 
            margin-top: 1.5rem; 
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Particles effect purely via CSS box-shadow for performance */
        .particles {
            position: absolute;
            width: 4px;
            height: 4px;
            background: transparent;
            box-shadow: 
                10vw 20vh #38bdf8, 30vw 40vh #818cf8, 50vw 80vh #22d3ee, 70vw 10vh #38bdf8, 90vw 60vh #818cf8,
                20vw 90vh #22d3ee, 40vw 30vh #38bdf8, 60vw 50vh #818cf8, 80vw 70vh #22d3ee;
            border-radius: 50%;
            animation: float-particles 20s linear infinite;
            z-index: 1;
            opacity: 0.4;
        }

        @keyframes float-particles {
            from { transform: translateY(0); }
            to { transform: translateY(-100vh); }
        }
        
        .form-control:not(:placeholder-shown) {
            color: white;
        }
    </style>
</head>
<body>

    <!-- Animated Tech Background Elements -->
    <div class="tech-bg"></div>
    <div class="tech-grid"></div>
    <div class="particles"></div>
    
    <!-- Duplicate particles for continuous flow -->
    <div class="particles" style="animation-delay: -10s;"></div>

    <!-- Login Glass Container -->
    <div class="login-glass-card">
        <?php if ($companyLogo): ?>
            <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo" class="login-logo">
        <?php else: ?>
            <div class="login-logo-placeholder"><i class="fas fa-building"></i></div>
        <?php endif; ?>
        
        <h1 class="company-title"><?= htmlspecialchars($companyName) ?></h1>
        <p class="login-subtitle">Secure System Authentication</p>
        
        <form action="controllers/auth.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            
            <div class="position-relative mb-3">
                <div class="form-floating">
                    <input type="text" class="form-control" id="loginId" name="login_id" placeholder="Login ID" required autocomplete="username">
                    <label for="loginId">Login ID</label>
                </div>
                <i class="fas fa-user input-icon"></i>
            </div>

            <div class="position-relative mb-2">
                <div class="form-floating">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Password" required autocomplete="current-password">
                    <label for="password">Password</label>
                </div>
                <i class="fas fa-lock input-icon"></i>
            </div>

            <div class="d-flex justify-content-end mb-4">
                <a href="forgot_password.php" class="text-decoration-none" style="color: var(--neon-cyan); font-size: 0.85rem; transition: color 0.2s; font-weight: 500;"><i class="fas fa-key me-1"></i> Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-login"><i class="fas fa-power-off me-2"></i> INITIALIZE SESSION</button>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="login-error">
                    <i class="fas fa-exclamation-triangle fs-5"></i> 
                    <div><?= htmlspecialchars($_GET['error']) ?></div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="login-success">
                    <i class="fas fa-check-circle fs-5"></i> 
                    <div><?= htmlspecialchars($_GET['success']) ?></div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple SW registration
        if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});

        // Hover effect for button text to make it feel more interactive
        const btn = document.querySelector('.btn-login');
        btn.addEventListener('mouseenter', () => {
            btn.innerHTML = '<i class="fas fa-fingerprint me-2"></i> AUTHENTICATE';
        });
        btn.addEventListener('mouseleave', () => {
            btn.innerHTML = '<i class="fas fa-power-off me-2"></i> INITIALIZE SESSION';
        });
    </script>
</body>
</html>
