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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Orbitron:wght@500;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    
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
        
        #particleCanvas {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 2;
            pointer-events: none;
        }

        /* Rotating Neon Border Wrapper */
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 444px;
            border-radius: 26px;
            padding: 2px; /* thickness of the neon border */
            overflow: hidden;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: conic-gradient(from 0deg, transparent 70%, var(--neon-cyan) 80%, var(--neon-blue) 100%);
            animation: spin-border 4s linear infinite;
            z-index: 0;
        }

        @keyframes spin-border {
            100% { transform: rotate(360deg); }
        }

        @keyframes slideUpFade {
            to { transform: translateY(0); opacity: 1; }
        }

        /* Glassmorphism Container */
        .login-glass-card {
            position: relative;
            z-index: 10;
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            height: 100%;
            box-shadow: inset 0 0 20px rgba(255,255,255,0.05);
            overflow: hidden; /* Contains the cyber scan */
        }
        
        /* Cyber Scan Line */
        .login-glass-card::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--neon-cyan), transparent);
            box-shadow: 0 0 10px var(--neon-cyan), 0 0 20px var(--neon-blue);
            animation: cyber-scan 3.5s ease-in-out infinite;
            z-index: 20;
            opacity: 0;
            pointer-events: none;
        }

        @keyframes cyber-scan {
            0% { top: -10px; opacity: 0; }
            10% { opacity: 0.8; }
            90% { opacity: 0.8; }
            100% { top: 105%; opacity: 0; }
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
            position: relative;
            z-index: 2;
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
            position: relative;
            z-index: 2;
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
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .login-subtitle { 
            font-family: 'Fira Code', monospace;
            font-size: 0.8rem; 
            color: var(--neon-cyan); 
            text-align: center;
            margin-bottom: 2rem; 
            height: 20px; /* fixed height to prevent layout shift during typewriter */
            position: relative;
            z-index: 2;
        }

        .cursor {
            display: inline-block;
            width: 8px;
            height: 15px;
            background-color: var(--neon-cyan);
            vertical-align: middle;
            animation: blink 1s step-end infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* Form Inputs */
        .form-floating {
            margin-bottom: 1.25rem;
            position: relative;
            z-index: 2;
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
            background: rgba(0, 0, 0, 0.4);
            border-color: var(--neon-cyan);
            color: #f8fafc;
            box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.15);
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
            text-shadow: 0 0 8px rgba(34, 211, 238, 0.5);
        }
        
        .form-control:not(:placeholder-shown) {
            color: white;
        }

        /* Button */
        .btn-login {
            background: linear-gradient(135deg, var(--neon-blue), var(--neon-cyan));
            border: none;
            border-radius: 12px;
            color: #0f172a;
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
            padding: 0.9rem;
            width: 100%;
            margin-top: 0.5rem;
            position: relative;
            z-index: 2;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.2);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34, 211, 238, 0.5);
            color: #0f172a;
        }

        .btn-login::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 50%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.5), transparent);
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
            position: relative;
            z-index: 2;
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
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>

    <!-- Animated Tech Background Elements -->
    <div class="tech-bg"></div>
    <div class="tech-grid"></div>
    <canvas id="particleCanvas"></canvas>

    <!-- Rotating Neon Border Wrapper -->
    <div class="login-wrapper">
        <!-- Login Glass Container -->
        <div class="login-glass-card">
            <?php if ($companyLogo): ?>
                <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo" class="login-logo">
            <?php else: ?>
                <div class="login-logo-placeholder"><i class="fas fa-building"></i></div>
            <?php endif; ?>
            
            <h1 class="company-title"><?= htmlspecialchars($companyName) ?></h1>
            <div class="login-subtitle" id="typewriter-subtitle"></div>
            
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

                <div class="d-flex justify-content-end mb-4 position-relative" style="z-index:2;">
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
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple SW registration
        if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});

        // Hover effect for button text
        const btn = document.querySelector('.btn-login');
        btn.addEventListener('mouseenter', () => {
            btn.innerHTML = '<i class="fas fa-fingerprint me-2"></i> AUTHENTICATE';
        });
        btn.addEventListener('mouseleave', () => {
            btn.innerHTML = '<i class="fas fa-power-off me-2"></i> INITIALIZE SESSION';
        });

        // Typewriter Effect
        const subtitle = document.getElementById('typewriter-subtitle');
        const text = "> Securing encrypted connection...";
        let i = 0;
        function typeWriter() {
            if (i < text.length) {
                subtitle.innerHTML = text.substring(0, i+1) + '<span class="cursor"></span>';
                i++;
                setTimeout(typeWriter, 50);
            }
        }
        setTimeout(typeWriter, 800);

        // Interactive Canvas Particles
        const canvas = document.getElementById('particleCanvas');
        const ctx = canvas.getContext('2d');
        let width = canvas.width = window.innerWidth;
        let height = canvas.height = window.innerHeight;
        let particles = [];
        let mouse = { x: null, y: null };

        window.addEventListener('mousemove', (e) => { mouse.x = e.x; mouse.y = e.y; });
        window.addEventListener('resize', () => { 
            width = canvas.width = window.innerWidth; 
            height = canvas.height = window.innerHeight; 
        });

        class Particle {
            constructor() {
                this.x = Math.random() * width;
                this.y = Math.random() * height;
                this.size = Math.random() * 2 + 0.5;
                this.speedX = Math.random() * 0.5 - 0.25;
                this.speedY = Math.random() * 0.5 - 0.25;
                this.color = Math.random() > 0.5 ? '#0ea5e9' : '#22d3ee';
                this.baseOpacity = Math.random() * 0.5 + 0.1;
            }
            update() {
                this.x += this.speedX;
                this.y += this.speedY;
                if (this.x > width) this.x = 0;
                if (this.x < 0) this.x = width;
                if (this.y > height) this.y = 0;
                if (this.y < 0) this.y = height;

                // Mouse interaction
                if (mouse.x != null) {
                    let dx = mouse.x - this.x;
                    let dy = mouse.y - this.y;
                    let distance = Math.sqrt(dx * dx + dy * dy);
                    if (distance < 150) {
                        // Move slightly towards mouse
                        this.x -= dx * 0.01;
                        this.y -= dy * 0.01;
                    }
                }
            }
            draw() {
                ctx.globalAlpha = this.baseOpacity;
                ctx.fillStyle = this.color;
                ctx.beginPath();
                ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                ctx.fill();
                ctx.shadowBlur = 10;
                ctx.shadowColor = this.color;
            }
        }
        
        for (let i = 0; i < 100; i++) particles.push(new Particle());

        function animate() {
            ctx.clearRect(0, 0, width, height);
            particles.forEach(p => { p.update(); p.draw(); });
            requestAnimationFrame(animate);
        }
        animate();
    </script>
</body>
</html>
