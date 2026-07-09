<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

requirePermission($pdo, 'manage_forms');

// Auto-init forms
$formsJson = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'dynamic_forms'")->fetchColumn();
if (!$formsJson) {
    $forms = [
        [
            'id' => 'form_' . uniqid(),
            'name' => 'General Inquiry Form',
            'active' => true,
            'fields' => [
                ['id' => uniqid(), 'label' => 'Full Name', 'name' => 'lead_name', 'type' => 'text', 'required' => true, 'options' => '', 'placeholder' => 'Enter your name'],
                ['id' => uniqid(), 'label' => 'Email Address', 'name' => 'email', 'type' => 'email', 'required' => true, 'options' => '', 'placeholder' => 'you@example.com'],
                ['id' => uniqid(), 'label' => 'Company', 'name' => 'company', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => 'Your company name'],
                ['id' => uniqid(), 'label' => 'Inquiry Type', 'name' => 'interest', 'type' => 'select', 'required' => false, 'options' => 'Sales|Support|Partnership', 'placeholder' => ''],
                ['id' => uniqid(), 'label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => true, 'options' => '', 'placeholder' => 'How can we help?']
            ]
        ]
    ];
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('dynamic_forms', ?)")->execute([json_encode($forms)]);
} else {
    $forms = json_decode($formsJson, true) ?: [];
}

// Handle Form Submission / Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) die("CSRF Validation Failed");

    // Rebuild the forms array cleanly
    $cleanForms = [];
    if (isset($_POST['forms']) && is_array($_POST['forms'])) {
        foreach ($_POST['forms'] as $formPost) {
        $cleanForm = [
            'id' => trim($formPost['id'] ?: 'form_' . uniqid()),
            'name' => trim($formPost['name']),
            'active' => isset($formPost['active']) && $formPost['active'] == '1',
            'fields' => []
        ];

        if (isset($formPost['fields']) && is_array($formPost['fields'])) {
            foreach ($formPost['fields'] as $fieldPost) {
                // Ensure field names are safe for HTML form inputs
                $cleanName = preg_replace('/[^a-zA-Z0-9_]/', '', trim($fieldPost['name'] ?? ''));
                if(empty($cleanName)) $cleanName = 'field_' . uniqid();

                $cleanForm['fields'][] = [
                    'id' => trim($fieldPost['id'] ?: uniqid()),
                    'label' => trim($fieldPost['label']),
                    'name' => $cleanName,
                    'type' => trim($fieldPost['type']),
                    'required' => isset($fieldPost['required']) && $fieldPost['required'] == '1',
                    'options' => trim($fieldPost['options'] ?? ''),
                    'placeholder' => trim($fieldPost['placeholder'] ?? '')
                ];
            }
        }
        $cleanForms[] = $cleanForm;
        }
    }

    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'dynamic_forms'")->execute([json_encode($cleanForms)]);
    $pdo->exec("INSERT INTO audit_trail (user_id, action, details) VALUES ('{$_SESSION['login_id']}', 'System Update', 'Updated Dynamic Forms')");
    
    header("Location: form_builder.php?success=Forms%20saved");
    exit();
}

// Add New Form
if (isset($_GET['add_form'])) {
    $forms[] = [
        'id' => 'form_' . uniqid(),
        'name' => 'New Blank Form',
        'active' => false,
        'fields' => [
            ['id' => uniqid(), 'label' => 'Full Name', 'name' => 'lead_name', 'type' => 'text', 'required' => true, 'options' => '', 'placeholder' => 'Enter your name']
        ]
    ];
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'dynamic_forms'")->execute([json_encode($forms)]);
    header("Location: form_builder.php");
    exit();
}

// Delete Form
if (isset($_GET['delete_form'])) {
    $id = $_GET['delete_form'];
    $forms = array_filter($forms, function($f) use ($id) { return $f['id'] !== $id; });
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'dynamic_forms'")->execute([json_encode(array_values($forms))]);
    header("Location: form_builder.php");
    exit();
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📝 Dynamic Form Builder</h2>
        <div style="display:flex; gap:10px;">
            <a href="form_builder.php?add_form=1" class="add-button" style="background:#4b5563;">+ Create New Form</a>
            <button class="add-button" onclick="document.getElementById('builderForm').submit();">💾 Save All Changes</button>
        </div>
    </div>

    <?php if(isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.2); border:1px solid #10b981; color:#34d399; padding: 15px; border-radius: 8px; margin-bottom: 24px;">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <form id="builderForm" method="POST">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <?php foreach ($forms as $fIndex => $form): ?>
        <div class="card" style="background:var(--bg-card); border:1px solid var(--border-card); padding:20px; border-radius:12px; margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid var(--border-card); padding-bottom:15px;">
                <div style="flex:1;">
                    <input type="hidden" name="forms[<?= $fIndex ?>][id]" value="<?= htmlspecialchars($form['id']) ?>">
                    <input type="text" name="forms[<?= $fIndex ?>][name]" value="<?= htmlspecialchars($form['name']) ?>" style="font-size:20px; font-weight:bold; width:300px; padding:8px; background:var(--glass-bg); color:white; border:1px solid var(--glass-border); border-radius:6px;">
                    <div style="margin-top:8px; font-size:12px; color:var(--text-muted);">
                        Public Link: <a href="public_form.php?id=<?= $form['id'] ?>" target="_blank" style="color:var(--accent);">public_form.php?id=<?= $form['id'] ?></a>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:16px;">
                    <label style="color:var(--text-body); font-weight:600; display:flex; align-items:center; gap:6px;">
                        <input type="hidden" name="forms[<?= $fIndex ?>][active]" value="0">
                        <input type="checkbox" name="forms[<?= $fIndex ?>][active]" value="1" <?= $form['active'] ? 'checked' : '' ?>> Form Active
                    </label>
                    <a href="form_builder.php?delete_form=<?= $form['id'] ?>" onclick="return confirm('Delete this form entirely?');" style="color:#ef4444; font-size:14px; text-decoration:none; font-weight:bold;">🗑️ Delete Form</a>
                </div>
            </div>

            <div id="fields_<?= $fIndex ?>" style="display:flex; flex-direction:column; gap:12px;">
                <?php foreach ($form['fields'] as $flIndex => $field): ?>
                <div class="field-row" style="background:rgba(0,0,0,0.2); padding:15px; border-radius:8px; display:grid; grid-template-columns:2fr 2fr 1fr 2fr 1fr 1fr auto; gap:10px; align-items:end;">
                    <input type="hidden" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][id]" value="<?= htmlspecialchars($field['id']) ?>">
                    
                    <div><label style="font-size:11px; color:var(--text-muted);">Label</label><input type="text" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][label]" value="<?= htmlspecialchars($field['label']) ?>" required></div>
                    <div><label style="font-size:11px; color:var(--text-muted);">Input Name (CRM Key)</label><input type="text" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][name]" value="<?= htmlspecialchars($field['name']) ?>" required></div>
                    
                    <div>
                        <label style="font-size:11px; color:var(--text-muted);">Type</label>
                        <select name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][type]">
                            <?php foreach(['text','email','tel','number','textarea','select','checkbox','radio'] as $t): ?>
                            <option value="<?= $t ?>" <?= $field['type'] === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div><label style="font-size:11px; color:var(--text-muted);">Options (A|B|C) / Placeholder</label><input type="text" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][options]" value="<?= htmlspecialchars(!empty($field['options']) ? $field['options'] : $field['placeholder']) ?>"></div>
                    
                    <div>
                        <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:10px;">Required</label>
                        <input type="hidden" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][required]" value="0">
                        <input type="checkbox" name="forms[<?= $fIndex ?>][fields][<?= $flIndex ?>][required]" value="1" <?= $field['required'] ? 'checked' : '' ?>>
                    </div>
                    
                    <div><button type="button" onclick="this.closest('.field-row').remove();" class="btn-primary" style="background:#ef4444; border:none; padding:8px 12px; font-size:12px;">Remove</button></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" class="btn-primary" style="margin-top:16px; background:#374151; border:1px solid #4b5563; font-size:13px;" onclick="addField(<?= $fIndex ?>)">+ Add Field</button>
        </div>
        <?php endforeach; ?>

    </form>
</div>

<script>
function addField(formIndex) {
    const container = document.getElementById('fields_' + formIndex);
    const fieldIndex = container.children.length;
    const uid = Math.random().toString(36).substr(2, 9);
    
    const html = `
        <div class="field-row" style="background:rgba(0,0,0,0.2); padding:15px; border-radius:8px; display:grid; grid-template-columns:2fr 2fr 1fr 2fr 1fr 1fr auto; gap:10px; align-items:end; animation: fadeIn 0.3s;">
            <input type="hidden" name="forms[${formIndex}][fields][${fieldIndex}][id]" value="${uid}">
            <div><label style="font-size:11px; color:var(--text-muted);">Label</label><input type="text" name="forms[${formIndex}][fields][${fieldIndex}][label]" value="New Field" required></div>
            <div><label style="font-size:11px; color:var(--text-muted);">Input Name (CRM Key)</label><input type="text" name="forms[${formIndex}][fields][${fieldIndex}][name]" value="field_${uid}" required></div>
            <div>
                <label style="font-size:11px; color:var(--text-muted);">Type</label>
                <select name="forms[${formIndex}][fields][${fieldIndex}][type]">
                    <option value="text">Text</option><option value="email">Email</option><option value="tel">Tel</option>
                    <option value="number">Number</option><option value="textarea">Textarea</option><option value="select">Select</option>
                    <option value="checkbox">Checkbox</option><option value="radio">Radio</option>
                </select>
            </div>
            <div><label style="font-size:11px; color:var(--text-muted);">Options (A|B) / Placeholder</label><input type="text" name="forms[${formIndex}][fields][${fieldIndex}][options]" value=""></div>
            <div>
                <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:10px;">Required</label>
                <input type="hidden" name="forms[${formIndex}][fields][${fieldIndex}][required]" value="0">
                <input type="checkbox" name="forms[${formIndex}][fields][${fieldIndex}][required]" value="1">
            </div>
            <div><button type="button" onclick="this.closest('.field-row').remove();" class="btn-primary" style="background:#ef4444; border:none; padding:8px 12px; font-size:12px;">Remove</button></div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
}
</script>

<style>
@keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
input[type="text"], input[type="email"], select, textarea {
    width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-card); background: var(--bg-body); color: var(--text-body);
}
</style>

<?php require_once 'includes/footer.php'; ?>
