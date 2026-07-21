<?php
session_start();
require_once 'includes/db.php';

// Prevent aggressive caching by Cloudflare/Edge proxies
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Removed hard redirect for logged-in users so Admins can preview the public website

$cWebsite = $GLOBAL_SETTINGS['enable_public_website'] ?? 'false';
if ($cWebsite === 'false') {
    // CMS Disabled: Route natively to private internal login.
    header("Location: login.php");
    exit();
}

$companyName = $GLOBAL_SETTINGS['company_name'] ?? 'Cyno Management';
$companyLogo = $GLOBAL_SETTINGS['company_logo'] ?? '';

$blocksJson = $GLOBAL_SETTINGS['public_website_blocks'] ?? null;
if (!$blocksJson) {
    $blocks = [
        ['type' => 'hero', 'visible' => true, 'title' => 'The Future of Corporate Management.', 'subtitle' => 'A completely unified ecosystem for tasks, HR, communication, and learning. Engineered for extreme modularity.'],
        ['type' => 'features', 'visible' => true, 'title' => 'Enterprise Modules', 'subtitle' => 'Discover our suite of fully integrated tools designed for extreme efficiency.'],
        ['type' => 'about', 'visible' => true, 'title' => 'Our Mission', 'subtitle' => 'We are dedicated to building scalable infrastructure that empowers global enterprises to achieve more with less friction.'],
        ['type' => 'careers', 'visible' => true, 'title' => 'Open Positions', 'subtitle' => 'Join our rapidly growing team of engineers and operators.']
    ];
} else {
    $blocks = json_decode($blocksJson, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyName) ?> - Enterprise Cloud</title>
    <link rel="manifest" href="manifest.json">
    <!-- Dark Glassmorphism CMS Styling -->
    <style>
        :root {
            --bg-base: #0f172a;
            --accent: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.5);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --glass-bg: rgba(30, 41, 59, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', system-ui, sans-serif; }
        body { background: var(--bg-base); color: var(--text-main); min-height: 100vh; overflow-x: hidden; }
        
        /* Ambient Background Blur */
        .ambient-glow { position: fixed; top: -20vh; left: 20vw; width: 60vw; height: 60vw; background: radial-gradient(circle, var(--accent-glow) 0%, transparent 70%); filter: blur(100px); z-index: -1; pointer-events: none; }
        .ambient-glow-2 { position: fixed; bottom: -20vh; right: -10vw; width: 50vw; height: 50vw; background: radial-gradient(circle, rgba(16, 185, 129, 0.3) 0%, transparent 70%); filter: blur(100px); z-index: -1; pointer-events: none; }

        /* Navbar */
        nav { display: flex; justify-content: space-between; align-items: center; padding: 24px 8%; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(12px); border-bottom: 1px solid var(--glass-border); position: sticky; top:0; z-index:100;}
        .logo { font-size: 24px; font-weight: 800; letter-spacing: -1px; display:flex; align-items:center; gap:10px; }
        .nav-links { display: flex; gap: 32px; align-items: center; }
        .nav-links a { color: var(--text-main); text-decoration: none; font-size: 15px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--accent); }
        .btn-primary { background: var(--accent); color: white; padding: 10px 24px; border-radius: 30px; text-decoration: none; font-weight: 600; transition: transform 0.2s, box-shadow 0.2s; border: none; cursor:pointer;}
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px var(--accent-glow); }

        /* Hero */
        .hero { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 120px 20px; min-height: 80vh; }
        .hero h1 { font-size: 64px; font-weight: 800; line-height: 1.1; margin-bottom: 24px; max-width: 900px; background: linear-gradient(to right, #fff, #94a3b8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 20px; color: var(--text-muted); max-width: 600px; margin-bottom: 40px; line-height: 1.6; }

        /* Careers Section */
        .section-title { font-size: 36px; text-align: center; margin: 80px 0 40px 0; }
        .careers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; padding: 0 8%; max-width: 1400px; margin: 0 auto 80px auto; }
        .glass-card { background: var(--glass-bg); backdrop-filter: blur(16px); border: 1px solid var(--glass-border); border-radius: 20px; padding: 32px; transition: transform 0.3s; }
        .glass-card:hover { transform: translateY(-5px); border-color: rgba(255,255,255,0.2); }
        .glass-card h3 { font-size: 24px; margin-bottom: 12px; }
        .glass-card p { color: var(--text-muted); font-size: 15px; margin-bottom: 24px; line-height: 1.5; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: var(--bg-base); border: 1px solid var(--glass-border); border-radius: 20px; padding: 40px; width: 100%; max-width: 500px; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid var(--glass-border); color: white; padding: 12px; border-radius: 8px; margin-top: 5px; outline:none; }
        .modal-content input:focus, .modal-content select:focus, .modal-content textarea:focus { border-color: var(--accent); }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            nav { flex-direction: column; gap: 20px; padding: 20px; }
            .nav-links { width: 100%; justify-content: center; flex-wrap: wrap; gap: 15px; }
            .hero h1 { font-size: 40px; padding: 0 10px; }
            .hero p { font-size: 16px; padding: 0 20px; }
            .hero { padding: 60px 10px; min-height: 60vh; }
            .hero-buttons { flex-direction: column; gap: 12px; width: 100%; padding: 0 20px; }
            .hero-buttons .btn-primary { width: 100%; text-align: center; }
            .careers-grid { grid-template-columns: 1fr; padding: 0 20px; }
            .section-title { font-size: 28px; margin: 40px 0 20px 0; }
        }
    </style>
</head>
<body>
    <div class="ambient-glow"></div>
    <div class="ambient-glow-2"></div>

    <nav>
        <div class="logo">
            <?php if (!empty($companyLogo)): ?>
                <img src="<?= htmlspecialchars($companyLogo) ?>" alt="Logo" style="height:36px; object-fit:contain; border-radius:4px;">
            <?php else: ?>
                <div style="width:30px; height:30px; background:var(--accent); border-radius:8px;"></div>
            <?php endif; ?>
            <?= htmlspecialchars($companyName) ?>
        </div>
        <div class="nav-links">
            <a href="#about">Platform</a>
            <a href="#careers">Careers</a>
            <?php if (isset($_SESSION['login_id'])): ?>
                <a href="dashboard.php" class="btn-primary" style="background:#1e293b; border:1px solid var(--glass-border);">Go to Dashboard</a>
            <?php else: ?>
                <a href="login.php" class="btn-primary" style="background:#1e293b; border:1px solid var(--glass-border);">Employee Login</a>
            <?php endif; ?>
            <button onclick="openCallModal()" class="btn-primary">Request a Call</button>
        </div>
    </nav>

    <?php if(isset($_GET['applied'])): ?>
        <?php if($_GET['applied'] === 'call_requested'): ?>
        <div style="background: rgba(16, 185, 129, 0.2); border:1px solid #10b981; color:#34d399; padding: 15px; border-radius: 8px; text-align:center; max-width: 600px; margin: 40px auto 10px auto;">
            Thank you! Your call request has been received. Our team will contact you shortly.
        </div>
        <?php else: ?>
        <div style="background: rgba(16, 185, 129, 0.2); border:1px solid #10b981; color:#34d399; padding: 15px; border-radius: 8px; text-align:center; max-width: 600px; margin: 40px auto 10px auto;">
            Your application was securely submitted to the Enterprise Matrix. Our HR team will evaluate your profile.
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php foreach($blocks as $block): ?>
        <?php if(!$block['visible']) continue; ?>

        <?php if($block['type'] === 'hero'): ?>
        <div class="hero">
            <div style="background: rgba(99,102,241,0.1); border:1px solid var(--accent); padding:6px 16px; border-radius:30px; color: #818cf8; font-size:13px; font-weight:600; margin-bottom:24px;">Enterprise OS 2.0</div>
            <h1><?= htmlspecialchars($block['title']) ?></h1>
            <p><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div class="hero-buttons" style="display:flex; gap:16px; margin-top:24px;">
                <a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary" style="padding:14px 32px; font-size:16px;"><?= htmlspecialchars($block['button_text']) ?></a>
            </div>
            <?php else: ?>
            <div class="hero-buttons" style="display:flex; gap:16px; margin-top:24px;">
                <button onclick="openCallModal()" class="btn-primary" style="padding:14px 32px; font-size:16px;">Request a Call</button>
                <a href="#careers" class="btn-primary" style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2); padding:14px 32px; font-size:16px;">View Careers</a>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'features'): ?>
        <div id="features" style="padding: 60px 8%; text-align:center;">
            <h2 class="section-title" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="color:var(--text-muted); max-width:600px; margin:0 auto 40px auto;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div class="careers-grid">
                <div class="glass-card">
                    <h3 style="font-size:20px; color:var(--accent);">HR & Payroll</h3>
                    <p>Automate onboarding, leave management, and complex payroll calculations natively.</p>
                </div>
                <div class="glass-card">
                    <h3 style="font-size:20px; color:#10b981;">Sales CRM</h3>
                    <p>Visual Kanban pipelines, automated webhook ingestion, and real-time activity tracking.</p>
                </div>
                <div class="glass-card">
                    <h3 style="font-size:20px; color:#f59e0b;">Project Tracking</h3>
                    <p>Gantt charts, interactive task boards, and detailed timesheet integrations.</p>
                </div>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="margin-top:40px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'about'): ?>
        <div id="about" style="padding: 60px 8%; background:rgba(15,23,42,0.6); text-align:center;">
            <h2 class="section-title" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="color:var(--text-muted); max-width:800px; margin:0 auto; line-height:1.8; font-size:18px;">
                <?= nl2br(htmlspecialchars($block['subtitle'])) ?>
            </p>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="margin-top:30px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'careers'): ?>
        <div style="padding: 60px 0;">
            <h2 class="section-title" id="careers" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="text-align:center; color:var(--text-muted); margin-bottom:40px;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div class="careers-grid">
                <div class="glass-card">
                    <h3>Senior Systems Architect</h3>
                    <p>Lead the architectural expansion of our new ERP systems. Require deep knowledge of SQLite and scalable PHP.</p>
                    <button onclick="openApply('Senior Systems Architect')" class="btn-primary" style="background:#1e293b; border:1px solid #334155;">Apply Now</button>
                </div>
                <div class="glass-card">
                    <h3>HR Operations Manager</h3>
                    <p>Manage onboarding funnels and configure global system roles utilizing the new Matrix Granular RBAC configurations.</p>
                    <button onclick="openApply('HR Operations Manager')" class="btn-primary" style="background:#1e293b; border:1px solid #334155;">Apply Now</button>
                </div>
                <div class="glass-card">
                    <h3>Cybersecurity Analyst</h3>
                    <p>Monitor the live Dashboard HUD and proactively defend against global CSRF and DOM injection attempts.</p>
                    <button onclick="openApply('Cybersecurity Analyst')" class="btn-primary" style="background:#1e293b; border:1px solid #334155;">Apply Now</button>
                </div>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="text-align:center; margin-top:20px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'testimonials'): ?>
        <div style="padding: 60px 8%; text-align:center; background:rgba(15,23,42,0.3);">
            <h2 class="section-title" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="color:var(--text-muted); max-width:600px; margin:0 auto 40px auto;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div class="careers-grid">
                <?php 
                $tests = json_decode($block['content'] ?? '[]', true);
                if(is_array($tests)): foreach($tests as $t): 
                ?>
                <div class="glass-card" style="text-align:left;">
                    <div style="color:var(--accent); font-size:24px; margin-bottom:10px;">"</div>
                    <p style="font-size:16px; color:#fff; font-style:italic;">"<?= htmlspecialchars($t['quote'] ?? '') ?>"</p>
                    <div style="color:var(--text-muted); font-size:14px; font-weight:600; margin-top:20px;">- <?= htmlspecialchars($t['author'] ?? '') ?></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="margin-top:40px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'pricing'): ?>
        <div style="padding: 60px 8%; text-align:center;">
            <h2 class="section-title" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="color:var(--text-muted); max-width:600px; margin:0 auto 40px auto;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div class="careers-grid">
                <?php 
                $plans = json_decode($block['content'] ?? '[]', true);
                if(is_array($plans)): foreach($plans as $p): 
                ?>
                <div class="glass-card" style="text-align:center; padding:40px 20px;">
                    <h3 style="font-size:24px; color:#fff;"><?= htmlspecialchars($p['name'] ?? '') ?></h3>
                    <div style="font-size:36px; font-weight:800; color:var(--accent); margin:20px 0;"><?= htmlspecialchars($p['price'] ?? '') ?></div>
                    <p style="color:var(--text-muted); font-size:15px; margin-bottom:30px;"><?= htmlspecialchars($p['features'] ?? '') ?></p>
                    <button onclick="openCallModal()" class="btn-primary" style="width:100%; background:transparent; border:1px solid var(--accent); color:var(--accent);">Select Plan</button>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="margin-top:40px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'faq'): ?>
        <div style="padding: 60px 8%; max-width:800px; margin:0 auto;">
            <h2 class="section-title" style="margin-top:0; text-align:center;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="text-align:center; color:var(--text-muted); margin-bottom:40px;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div>
                <?php 
                $faqs = json_decode($block['content'] ?? '[]', true);
                if(is_array($faqs)): foreach($faqs as $f): 
                ?>
                <div style="background:var(--glass-bg); border:1px solid var(--glass-border); border-radius:12px; margin-bottom:16px; padding:20px;">
                    <h3 style="font-size:18px; margin-bottom:10px; color:#fff;"><?= htmlspecialchars($f['q'] ?? '') ?></h3>
                    <p style="color:var(--text-muted); font-size:15px; margin:0; line-height:1.6;"><?= htmlspecialchars($f['a'] ?? '') ?></p>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div style="text-align:center; margin-top:40px;"><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>

        <?php elseif($block['type'] === 'custom_html'): ?>
        <div style="padding: 60px 8%; max-width:1200px; margin:0 auto; text-align:center;">
            <h2 class="section-title" style="margin-top:0;"><?= htmlspecialchars($block['title']) ?></h2>
            <p style="color:var(--text-muted); margin-bottom:40px;"><?= nl2br(htmlspecialchars($block['subtitle'])) ?></p>
            <div style="margin-bottom:40px;">
                <?= $block['content'] ?? '' ?>
            </div>
            <?php if(!empty($block['button_text']) && !empty($block['button_url'])): ?>
            <div><a href="<?= htmlspecialchars($block['button_url']) ?>" class="btn-primary"><?= htmlspecialchars($block['button_text']) ?></a></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php endforeach; ?>

    <!-- Onboarding Application Modal -->
    <div class="modal" id="applyModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
                <h2 id="applyTitle">Apply Now</h2>
                <button onclick="closeApply()" style="background:none; border:none; color:white; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <form action="controllers/submit_onboarding.php" method="POST">
                <input type="hidden" name="position" id="applyPosition">
                
                <div style="margin-bottom:16px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div><label>First Name</label><input type="text" name="first_name" required></div>
                    <div><label>Last Name</label><input type="text" name="last_name" required></div>
                </div>
                
                <div style="margin-bottom:16px;">
                    <label>Email Address</label>
                    <input type="email" name="email" required>
                </div>

                <div style="margin-bottom:16px;">
                    <label>Resume / Portfolio Link</label>
                    <input type="url" name="resume_link" placeholder="https://linkedin.com/in/... or Google Drive" required>
                </div>
                
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">Submit Application</button>
            </form>
        </div>
    </div>

    <!-- Request a Call Modal -->
    <div class="modal" id="callModal">
        <div class="modal-content">
            <div style="display:flex; justify-content:space-between; margin-bottom:24px;">
                <h2>Request a Call</h2>
                <button onclick="closeCallModal()" style="background:none; border:none; color:white; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <p style="color:var(--text-muted); margin-bottom: 20px; font-size: 14px;">Fill out your details and our enterprise sales team will contact you shortly.</p>
            <form action="controllers/submit_call_request.php" method="POST">
                <div style="margin-bottom:16px;">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                <div style="margin-bottom:16px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div><label>Email Address</label><input type="email" name="email" required></div>
                    <div><label>Phone Number</label><input type="text" name="phone" required></div>
                </div>
                <div style="margin-bottom:16px;">
                    <label>Company / Organization</label>
                    <input type="text" name="company" required>
                </div>
                <div style="margin-bottom:16px;">
                    <label>How can we help?</label>
                    <textarea name="notes" rows="3" placeholder="Tell us about your requirements..."></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top:10px;">Submit Request</button>
            </form>
        </div>
    </div>

    <script>
        function openApply(position) {
            document.getElementById('applyPosition').value = position;
            document.getElementById('applyTitle').innerText = 'Apply: ' + position;
            document.getElementById('applyModal').style.display = 'flex';
        }
        function closeApply() {
            document.getElementById('applyModal').style.display = 'none';
        }
        function openCallModal() {
            document.getElementById('callModal').style.display = 'flex';
        }
        function closeCallModal() {
            document.getElementById('callModal').style.display = 'none';
        }
    </script>
    <script>
      if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});
    </script>
</body>
</html>
