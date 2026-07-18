<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

// 1. Initialize Table
$pdo->exec("CREATE TABLE IF NOT EXISTS company_benefits (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    description TEXT,
    icon TEXT,
    color_gradient TEXT,
    action_text TEXT
)");

// 2. Insert Defaults if empty
$count = $pdo->query("SELECT COUNT(*) FROM company_benefits")->fetchColumn();
if ($count == 0) {
    $pdo->exec("INSERT INTO company_benefits (title, description, icon, color_gradient, action_text) VALUES 
    ('Comprehensive Health Insurance', 'Full medical, dental, and vision coverage for you and your dependents through our premium provider network.', 'fas fa-notes-medical', 'linear-gradient(135deg, #3b82f6, #2563eb)', 'View Plan Details'),
    ('Mental Wellness Program', 'Free access to licensed therapists, meditation apps, and wellness days to keep your mind healthy and focused.', 'fas fa-brain', 'linear-gradient(135deg, #10b981, #059669)', 'Access Resources'),
    ('Fitness Memberships', 'Get reimbursed up to $100/month for gym memberships, fitness classes, or home workout equipment.', 'fas fa-dumbbell', 'linear-gradient(135deg, #f59e0b, #d97706)', 'Submit Reimbursement'),
    ('401(k) Matching', 'Secure your future with our 401(k) program. The company matches up to 5% of your contributions.', 'fas fa-piggy-bank', 'linear-gradient(135deg, #8b5cf6, #6d28d9)', 'Manage Retirement')");
}

// 3. Handle Add/Delete requests
$isAdmin = in_array($_SESSION['role'], ['Admin', 'Super Admin', 'HR Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO company_benefits (title, description, icon, color_gradient, action_text) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['icon'] ?: 'fas fa-star',
            $_POST['color_gradient'] ?: 'linear-gradient(135deg, #3b82f6, #2563eb)',
            $_POST['action_text'] ?: 'Learn More'
        ]);
        header('Location: benefits.php');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM company_benefits WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: benefits.php');
        exit;
    }
}

// 4. Fetch Benefits
$benefits = $pdo->query("SELECT * FROM company_benefits ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active" style="padding-top:0;">
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #ec4899, #8b5cf6); border-radius: 0 0 24px 24px; padding: 40px 40px 80px 40px; margin: -20px -20px 20px -20px; color: white; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(236, 72, 153, 0.2);">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px;"><i class="fas fa-heartbeat" style="margin-right:10px;"></i> Health & Benefits</h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.9;">Your well-being is our priority. Explore your corporate perks and health coverage.</p>
            </div>
            <div style="display:flex; gap:12px;">
                <?php if($isAdmin): ?>
                <button onclick="document.getElementById('addModal').style.display='flex'" style="background: white; color: #ec4899; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <i class="fas fa-plus"></i> Add Benefit
                </button>
                <?php endif; ?>
                <button onclick="alert('Feature coming soon: Download Benefits PDF')" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <i class="fas fa-download"></i> Download Summary
                </button>
            </div>
        </div>
        <div style="position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(30px);"></div>
    </div>

    <div style="margin-top: -50px; position: relative; z-index: 5; padding: 0 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px; max-width: 1200px; margin-left: auto; margin-right: auto;">
        
        <?php foreach($benefits as $b): 
            $icon = htmlspecialchars($b['icon']);
            $color = htmlspecialchars($b['color_gradient']);
            // Extract the first color from gradient for the text link color
            preg_match('/#([a-f0-9]{6}|[a-f0-9]{3})/i', $color, $matches);
            $textColor = $matches[0] ?? '#3b82f6';
        ?>
        <div class="glass-card" style="position:relative; padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer; background: var(--bg-card); border: 1px solid var(--border-card);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
            <?php if($isAdmin): ?>
            <form method="POST" style="position:absolute; top:20px; right:20px;" onsubmit="return confirm('Delete this benefit?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                <button type="submit" style="background:rgba(239, 68, 68, 0.1); color:#ef4444; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer;"><i class="fas fa-trash"></i></button>
            </form>
            <?php endif; ?>
            
            <div style="width: 50px; height: 50px; background: <?= $color ?>; color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <i class="<?= $icon ?>"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);"><?= htmlspecialchars($b['title']) ?></h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;"><?= htmlspecialchars($b['description']) ?></p>
            <div style="font-weight: 600; color: <?= $textColor ?>; font-size: 14px; display: flex; align-items: center; gap: 5px;">
                <?= htmlspecialchars($b['action_text']) ?> <i class="fas fa-arrow-right"></i>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if(empty($benefits)): ?>
        <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: var(--text-muted);">
            <h3>No benefits listed yet.</h3>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php if($isAdmin): ?>
<!-- Add Benefit Modal -->
<div class="modal premium-modal" id="addModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="width: 500px; background: var(--bg-card); padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <h2 style="margin: 0 0 20px 0; color: var(--text-heading); font-size: 22px; font-weight: 800;">Add New Benefit</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Benefit Title</label>
                <input type="text" name="title" required placeholder="e.g. Pet Insurance" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Description</label>
                <textarea name="description" required rows="3" placeholder="Explain the benefit..." style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none; resize:none;"></textarea>
            </div>
            
            <div style="margin-bottom: 16px; display:flex; gap:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Icon Class</label>
                    <input type="text" name="icon" placeholder="fas fa-paw" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Call to Action</label>
                    <input type="text" name="action_text" placeholder="Enroll Now" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="margin-bottom: 24px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Color Gradient (CSS)</label>
                <select name="color_gradient" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                    <option value="linear-gradient(135deg, #3b82f6, #2563eb)">Blue Gradient</option>
                    <option value="linear-gradient(135deg, #10b981, #059669)">Green Gradient</option>
                    <option value="linear-gradient(135deg, #f59e0b, #d97706)">Orange Gradient</option>
                    <option value="linear-gradient(135deg, #ec4899, #be185d)">Pink Gradient</option>
                    <option value="linear-gradient(135deg, #8b5cf6, #6d28d9)">Purple Gradient</option>
                </select>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background: rgba(0,0,0,0.05); color: var(--text-heading); border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" class="premium-btn">Save Benefit</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
