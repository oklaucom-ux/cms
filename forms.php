<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto Migrate
$pdo->exec("CREATE TABLE IF NOT EXISTS dynamic_forms (id INTEGER PRIMARY KEY AUTO_INCREMENT, title TEXT, frequency TEXT, schema_json TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS form_assignments (id INTEGER PRIMARY KEY AUTO_INCREMENT, form_id INTEGER, assigned_to TEXT)");
$pdo->exec("CREATE TABLE IF NOT EXISTS form_submissions (id INTEGER PRIMARY KEY AUTO_INCREMENT, form_id INTEGER, user_id TEXT, data_json TEXT, submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

// Auto-migrate missing columns
try { $pdo->exec("ALTER TABLE dynamic_forms ADD COLUMN is_public INTEGER DEFAULT 0"); } catch (Exception $e) {}

$isAdmin = hasPermission($pdo, 'manage_forms');
$me = $_SESSION['login_id'];

// Fetch Users for Assignment
$all_users = $pdo->query("SELECT login_id, name FROM users")->fetchAll(PDO::FETCH_ASSOC);

if ($isAdmin) {
    $myForms = $pdo->query("SELECT * FROM dynamic_forms")->fetchAll(PDO::FETCH_ASSOC);
    $submissions = $pdo->query("SELECT s.*, f.title FROM form_submissions s JOIN dynamic_forms f ON s.form_id = f.id ORDER BY s.submitted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Only assigned forms
    $stmt = $pdo->prepare("SELECT f.* FROM dynamic_forms f JOIN form_assignments a ON f.id = a.form_id WHERE a.assigned_to = ?");
    $stmt->execute([$me]);
    $myForms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $submissions = [];
}
?>

<div class="content-section active">
    <!-- Header -->
    <div class="section-header">
        <h2>Dynamic Form Engine</h2>
        <?php if($isAdmin): ?>
        <button class="add-button" onclick="openBuilderModal()">+ Build New Form</button>
        <?php endif; ?>
    </div>

    <!-- Active Forms Grid -->
    <h3 style="margin-bottom: 16px; color: #374151;">Available Forms</h3>
    <div class="dashboard-grid">
        <?php foreach($myForms as $form): ?>
            <div class="dashboard-card" style="">
                <h3 style="font-size: 20px;"><?= htmlspecialchars($form['title']) ?></h3>
                <p style="margin-bottom: 8px;">Frequency: <strong><?= htmlspecialchars($form['frequency']) ?></strong></p>
                <?php if(isset($form['is_public']) && $form['is_public']): ?>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10B981; margin-bottom: 16px; display: inline-block;">Public Lead Form</span>
                <?php else: ?>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.1); color: #4f46e5; margin-bottom: 16px; display: inline-block;">Internal Assignment</span>
                <?php endif; ?>

                
                <?php if(!$isAdmin): ?>
                    <button class="edit-button" onclick='openFillModal(<?= json_encode($form) ?>)' style="width:100%; padding: 10px;">Fill Data</button>
                <?php else: ?>
                    <div style="display:flex; gap:10px;">
                        <form method="POST" action="controllers/delete_form.php" onsubmit="return confirm('Delete this form entirely?')" style="flex:1;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="id" value="<?= $form['id'] ?>">
                            <button type="submit" class="delete-button" style="width:100%;">Delete</button>
                        </form>
                        <?php if(isset($form['is_public']) && $form['is_public']): ?>
                            <button class="view-button" style="flex:1;" onclick="copyEmbed(<?= $form['id'] ?>)">Copy Embed</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if(empty($myForms)): ?>
            <p style="color:#6b7280;">No forms available right now.</p>
        <?php endif; ?>
    </div>

    <!-- Submissions View for Admin -->
    <?php if($isAdmin): ?>
    <h3 style="margin-top: 32px; margin-bottom: 16px; color: #374151;">Recent Form Submissions</h3>
    <div class="data-table">
        <table>
            <thead>
                <tr>
                    <th>Form Title</th>
                    <th>User ID</th>
                    <th>Submitted At</th>
                    <th>Data Summary</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($submissions as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['title']) ?></strong></td>
                    <td><?= htmlspecialchars($s['user_id']) ?></td>
                    <td><?= htmlspecialchars($s['submitted_at']) ?></td>
                    <td>
                        <button class="view-button" onclick='viewData(<?= $s['data_json'] ?>)'>View Answers</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Builder Modal (Admin) -->
<div id="builderModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <span class="close-button" onclick="document.getElementById('builderModal').style.display='none'">&times;</span>
        <h2>Form Builder Setup</h2>
        
        <form method="POST" action="controllers/save_form.php" id="builderForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Form Title</label>
                <input type="text" name="title" required placeholder="e.g., Daily Store Checkout">
            </div>
            
            <div class="form-group">
                <label>Allocation Frequency</label>
                <select name="frequency">
                    <option value="Daily">Daily</option>
                    <option value="Weekly">Weekly</option>
                    <option value="Per Visit">Per Visit Tracker</option>
                </select>
            </div>
            
            <div class="form-group" style="background: var(--bg-hover); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                <label style="display:flex; align-items:center; gap: 12px; cursor: pointer; margin:0;">
                    <input type="checkbox" name="is_public" value="1" style="width:18px; height:18px;">
                    <div>
                        <strong style="display:block; color: var(--text-heading);">Public Form (Lead Capture)</strong>
                        <span style="font-size:0.85em; color:var(--text-muted); font-weight:normal; display:block; margin-top:4px;">Check this to allow external unauthenticated submission. We'll generate an HTML snippet.</span>
                    </div>
                </label>
            </div>

            <div class="form-group">
                <label>Assign to Users</label>
                <select name="assigned_users[]" multiple style="height: 100px;">
                    <option value="ALL">ALL USERS</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?= $u['login_id'] ?>"><?= $u['name'] ?> (<?= $u['login_id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#6b7280;">Hold CTRL/CMD to select multiple.</small>
            </div>

            <hr style="margin:24px 0; border:0; border-top:1px solid #e5e7eb;">
            <h3>Form Fields</h3>
            <div id="dynamicFieldsContainer" style="background: #f9fafb; padding: 16px; border-radius: 8px; margin-bottom:16px;"></div>
            
            <div style="display:flex; gap:10px; margin-bottom:24px;">
                <input type="text" id="newFieldLabel" placeholder="Question / Field Label" style="flex:1; padding:8px; border-radius:6px; border:1px solid #ccc;">
                <select id="newFieldType" style="padding:8px; border-radius:6px; border:1px solid #ccc;">
                    <option value="text">Short Text</option>
                    <option value="textarea">Long Paragraph</option>
                    <option value="email">Email Address</option>
                    <option value="number">Number / Quantity</option>
                    <option value="date">Date Selection</option>
                </select>
                <button type="button" class="view-button" onclick="addField()">+ Add Field</button>
            </div>

            <input type="hidden" name="schema_json" id="schemaJSON">
            
            <div class="form-actions">
                <button type="submit" class="submit">Save & Deploy Form</button>
            </div>
        </form>
    </div>
</div>

<!-- Fill Modal (Employee) -->
<div id="fillModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('fillModal').style.display='none'">&times;</span>
        <h2 id="fillTitle">Fill Form</h2>
        <form method="POST" action="controllers/submit_form.php" id="fillForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="form_id" id="fillFormId">
            <div id="fillFieldsContainer"></div>
            <div class="form-actions">
                <button type="submit" class="submit">Submit Data</button>
            </div>
        </form>
    </div>
</div>

<!-- View Data Modal (Admin) -->
<div id="viewDataModal" class="modal">
    <div class="modal-content">
        <span class="close-button" onclick="document.getElementById('viewDataModal').style.display='none'">&times;</span>
        <h2>Submission Answers</h2>
        <div id="viewDataContainer" style="margin-top: 16px; display: flex; flex-direction: column; gap: 12px; max-height: 400px; overflow-y: auto;">
        </div>
    </div>
</div>

<script>
// Admin Builder Logic
let customFields = [];

function openBuilderModal() {
    customFields = [];
    renderFields();
    document.getElementById('builderModal').style.display='block';
}

function addField() {
    let label = document.getElementById('newFieldLabel').value.trim();
    let type = document.getElementById('newFieldType').value;
    if(!label) return alert("Enter a field label!");
    
    customFields.push({ label: label, type: type });
    document.getElementById('newFieldLabel').value = '';
    renderFields();
}

function copyEmbed(formId) {
    let loc = window.location;
    let baseUrl = loc.protocol + "//" + loc.host + loc.pathname.replace('forms.php', '');
    let embedCode = `<iframe src="${baseUrl}embed_form.php?id=${formId}" width="100%" height="600" style="border:none; border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.05);"></iframe>`;
    navigator.clipboard.writeText(embedCode).then(() => {
        alert("Embed code copied to clipboard! Paste it on your external website.");
    });
}

function renderFields() {
    let html = '';
    customFields.forEach((f, index) => {
        html += `<div style="padding:8px; background:white; margin-bottom:8px; border:1px solid #ddd; border-radius:4px; display:flex; justify-content:space-between;">
            <span><strong>${f.label}</strong> (${f.type})</span>
            <span style="color:red; cursor:pointer;" onclick="customFields.splice(${index}, 1); renderFields();">x</span>
        </div>`;
    });
    document.getElementById('dynamicFieldsContainer').innerHTML = html || '<span style="color:#999;">No fields added yet. Add one below!</span>';
    document.getElementById('schemaJSON').value = JSON.stringify(customFields);
}

// User Fill Logic
function openFillModal(formObj) {
    document.getElementById('fillTitle').textContent = formObj.title;
    document.getElementById('fillFormId').value = formObj.id;
    
    let schema = JSON.parse(formObj.schema_json);
    let html = '';
    
    schema.forEach(field => {
        html += `<div class="form-group">
            <label>${field.label}</label>
            <input type="${field.type}" name="custom_data[${field.label}]" required class="form-control">
        </div>`;
    });
    
    document.getElementById('fillFieldsContainer').innerHTML = html;
    document.getElementById('fillModal').style.display = 'block';
}

function viewData(jsonObj) {
    let html = '';
    for(let key in jsonObj) {
        html += `<div style="background: var(--bg-hover); padding: 12px; border-radius: 8px; border: 1px solid var(--border-color);">
            <strong style="color: var(--primary-color); display: block; margin-bottom: 4px;">${key}</strong>
            <span style="color: var(--text-body); white-space: pre-wrap;">${jsonObj[key]}</span>
        </div>`;
    }
    document.getElementById('viewDataContainer').innerHTML = html;
    document.getElementById('viewDataModal').style.display = 'block';
}
</script>

<?php require_once 'includes/footer.php'; ?>
