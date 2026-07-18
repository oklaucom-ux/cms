<?php
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['login_id'])) {
    header('Location: login.php');
    exit;
}

// 1. Initialize Table
$autoIncrement = isset($use_mysql) && $use_mysql ? 'AUTO_INCREMENT' : 'AUTOINCREMENT';
$pdo->exec("CREATE TABLE IF NOT EXISTS company_benefits (
    id INTEGER PRIMARY KEY $autoIncrement,
    title TEXT,
    description TEXT,
    icon TEXT,
    color_gradient TEXT,
    action_text TEXT,
    link_url TEXT
)");

// Check and add link_url column if it doesn't exist (for existing tables)
try {
    $pdo->exec("ALTER TABLE company_benefits ADD COLUMN link_url TEXT");
} catch (Exception $e) {
    // Column likely already exists
}

// 2. Insert Defaults if never seeded
if (empty($GLOBAL_SETTINGS['benefits_seeded'])) {
    $pdo->exec("INSERT INTO company_benefits (title, description, icon, color_gradient, action_text, link_url) VALUES 
    ('Comprehensive Health Insurance', 'Full medical, dental, and vision coverage for you and your dependents through our premium provider network.', 'fas fa-notes-medical', 'linear-gradient(135deg, #3b82f6, #2563eb)', 'View Plan Details', '#'),
    ('Mental Wellness Program', 'Free access to licensed therapists, meditation apps, and wellness days to keep your mind healthy and focused.', 'fas fa-brain', 'linear-gradient(135deg, #10b981, #059669)', 'Access Resources', '#'),
    ('Fitness Memberships', 'Get reimbursed up to $100/month for gym memberships, fitness classes, or home workout equipment.', 'fas fa-dumbbell', 'linear-gradient(135deg, #f59e0b, #d97706)', 'Submit Reimbursement', '#'),
    ('401(k) Matching', 'Secure your future with our 401(k) program. The company matches up to 5% of your contributions.', 'fas fa-piggy-bank', 'linear-gradient(135deg, #8b5cf6, #6d28d9)', 'Manage Retirement', '#')");
    
    // Mark as seeded
    $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('benefits_seeded', '1')");
    $GLOBAL_SETTINGS['benefits_seeded'] = '1';
}

// 3. Handle Add/Edit/Delete requests
$isAdmin = in_array($_SESSION['role'], ['Admin', 'Super Admin', 'HR Manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO company_benefits (title, description, icon, color_gradient, action_text, link_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['icon'] ?: 'fas fa-star',
            $_POST['color_gradient'] ?: 'linear-gradient(135deg, #3b82f6, #2563eb)',
            $_POST['action_text'] ?: 'Learn More',
            $_POST['link_url'] ?: '#'
        ]);
        header('Location: benefits.php');
        exit;
    }
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE company_benefits SET title=?, description=?, icon=?, color_gradient=?, action_text=?, link_url=? WHERE id=?");
        $stmt->execute([
            $_POST['title'],
            $_POST['description'],
            $_POST['icon'] ?: 'fas fa-star',
            $_POST['color_gradient'] ?: 'linear-gradient(135deg, #3b82f6, #2563eb)',
            $_POST['action_text'] ?: 'Learn More',
            $_POST['link_url'] ?: '#',
            $_POST['id']
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

// Require UI templates AFTER handling all redirects and headers
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

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
            $icon = htmlspecialchars($b['icon'] ?? '');
            $color = htmlspecialchars($b['color_gradient'] ?? '');
            // Extract the first color from gradient for the text link color
            preg_match('/#([a-f0-9]{6}|[a-f0-9]{3})/i', $color, $matches);
            $textColor = $matches[0] ?? '#3b82f6';
            $linkUrl = htmlspecialchars($b['link_url'] ?? '#');
        ?>
        <div class="glass-card" style="position:relative; padding: 30px; border-radius: 20px; transition: transform 0.2s, box-shadow 0.2s; background: var(--bg-card); border: 1px solid var(--border-card);" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 15px 35px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
            <?php if($isAdmin): ?>
            <div style="position:absolute; top:20px; right:20px; display:flex; gap:8px;">
                <!-- Edit Button -->
                <button onclick='openEditModal(<?= json_encode($b) ?>)' style="background:rgba(59, 130, 246, 0.1); color:#3b82f6; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer;"><i class="fas fa-edit"></i></button>
                <!-- Delete Button -->
                <form method="POST" onsubmit="return confirm('Delete this benefit?');" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                    <button type="submit" style="background:rgba(239, 68, 68, 0.1); color:#ef4444; border:none; width:30px; height:30px; border-radius:8px; cursor:pointer;"><i class="fas fa-trash"></i></button>
                </form>
            </div>
            <?php endif; ?>
            
            <div style="width: 50px; height: 50px; background: <?= $color ?>; color: white; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                <i class="<?= $icon ?>"></i>
            </div>
            <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: var(--text-heading);"><?= htmlspecialchars($b['title'] ?? '') ?></h3>
            <p style="color: var(--text-muted); font-size: 15px; line-height: 1.6; margin-bottom: 20px;"><?= htmlspecialchars($b['description'] ?? '') ?></p>
            <a href="<?= $linkUrl ?>" style="font-weight: 600; color: <?= $textColor ?>; font-size: 14px; display: inline-flex; align-items: center; gap: 5px; text-decoration:none;">
                <?= htmlspecialchars($b['action_text'] ?? '') ?> <i class="fas fa-arrow-right"></i>
            </a>
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
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Color Gradient (CSS)</label>
                    <select name="color_gradient" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                        <option value="linear-gradient(135deg, #3b82f6, #2563eb)">Blue Gradient</option>
                        <option value="linear-gradient(135deg, #10b981, #059669)">Green Gradient</option>
                        <option value="linear-gradient(135deg, #f59e0b, #d97706)">Orange Gradient</option>
                        <option value="linear-gradient(135deg, #ec4899, #be185d)">Pink Gradient</option>
                        <option value="linear-gradient(135deg, #8b5cf6, #6d28d9)">Purple Gradient</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 24px; display:flex; gap:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Link Text</label>
                    <input type="text" name="action_text" placeholder="Enroll Now" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Link URL</label>
                    <input type="text" name="link_url" placeholder="https://..." style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('addModal').style.display='none'" style="background: rgba(0,0,0,0.05); color: var(--text-heading); border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" class="premium-btn">Save Benefit</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Benefit Modal -->
<div class="modal premium-modal" id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.6); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="width: 500px; background: var(--bg-card); padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <h2 style="margin: 0 0 20px 0; color: var(--text-heading); font-size: 22px; font-weight: 800;">Edit Benefit</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id" value="">
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Benefit Title</label>
                <input type="text" name="title" id="edit_title" required style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Description</label>
                <textarea name="description" id="edit_description" required rows="3" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none; resize:none;"></textarea>
            </div>
            
            <div style="margin-bottom: 16px; display:flex; gap:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Icon Class</label>
                    <input type="text" name="icon" id="edit_icon" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Color Gradient (CSS)</label>
                    <select name="color_gradient" id="edit_color" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                        <option value="linear-gradient(135deg, #3b82f6, #2563eb)">Blue Gradient</option>
                        <option value="linear-gradient(135deg, #10b981, #059669)">Green Gradient</option>
                        <option value="linear-gradient(135deg, #f59e0b, #d97706)">Orange Gradient</option>
                        <option value="linear-gradient(135deg, #ec4899, #be185d)">Pink Gradient</option>
                        <option value="linear-gradient(135deg, #8b5cf6, #6d28d9)">Purple Gradient</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 24px; display:flex; gap:16px;">
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Link Text</label>
                    <input type="text" name="action_text" id="edit_action_text" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; margin-bottom:8px; font-weight:600; color:var(--text-muted); font-size:13px;">Link URL</label>
                    <input type="text" name="link_url" id="edit_link_url" style="width:100%; padding:12px 16px; border:1px solid var(--border-card); border-radius:10px; background:var(--input-bg); color:var(--text-body); outline:none;">
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="background: rgba(0,0,0,0.05); color: var(--text-heading); border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button type="submit" class="premium-btn">Update Benefit</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(benefit) {
    document.getElementById('edit_id').value = benefit.id;
    document.getElementById('edit_title').value = benefit.title;
    document.getElementById('edit_description').value = benefit.description;
    document.getElementById('edit_icon').value = benefit.icon;
    document.getElementById('edit_color').value = benefit.color_gradient;
    document.getElementById('edit_action_text').value = benefit.action_text;
    document.getElementById('edit_link_url').value = benefit.link_url || '#';
    document.getElementById('editModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
