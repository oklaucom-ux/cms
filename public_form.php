<?php
session_start();
require_once 'includes/db.php';

$formId = $_GET['id'] ?? '';
if (!$formId) die("Form ID is missing.");

$formsJson = $GLOBAL_SETTINGS['dynamic_forms'] ?? '';
$forms = json_decode($formsJson, true) ?: [];

$targetForm = null;
foreach ($forms as $f) {
    if ($f['id'] === $formId && $f['active']) {
        $targetForm = $f;
        break;
    }
}

if (!$targetForm) die("Form is inactive or does not exist.");

$companyName = $GLOBAL_SETTINGS['company_name'] ?? 'Cyno Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($targetForm['name']) ?> - <?= htmlspecialchars($companyName) ?></title>
    <style>
        :root {
            --bg-body: #0b1120;
            --bg-card: rgba(30, 41, 59, 0.7);
            --border-card: rgba(255, 255, 255, 0.1);
            --text-body: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #6366f1;
            --accent-hover: #4f46e5;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; background: var(--bg-body); color: var(--text-body); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .form-container { background: var(--bg-card); border: 1px solid var(--border-card); backdrop-filter: blur(12px); border-radius: 16px; padding: 40px; width: 100%; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        h1 { margin-top: 0; font-size: 24px; color: #fff; margin-bottom: 8px; }
        p { color: var(--text-muted); margin-bottom: 30px; font-size: 14px; line-height: 1.5; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #cbd5e1; }
        input[type="text"], input[type="email"], input[type="tel"], input[type="number"], select, textarea {
            width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid #334155; background: rgba(15, 23, 42, 0.6); color: white; font-family: inherit; font-size: 14px; transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); }
        .btn-primary { background: var(--accent); color: white; border: none; padding: 14px 24px; border-radius: 8px; font-size: 15px; font-weight: 600; cursor: pointer; width: 100%; transition: background 0.2s; }
        .btn-primary:hover { background: var(--accent-hover); }
        .radio-group, .checkbox-group { display: flex; flex-direction: column; gap: 10px; }
        .radio-group label, .checkbox-group label { display: flex; align-items: center; gap: 8px; font-weight: 400; margin-bottom: 0; cursor: pointer; }
        .success-msg { display: none; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #34d399; padding: 16px; border-radius: 8px; text-align: center; font-weight: 500; }
    </style>
</head>
<body>

<div class="form-container">
    <?php if(isset($_GET['success'])): ?>
    <div class="success-msg" style="display:block;">
        ✅ Thank you! Your submission has been securely recorded.
    </div>
    <div style="text-align:center; margin-top:20px;">
        <button class="btn-primary" onclick="window.location.href='index.php'">Return to Home</button>
    </div>
    <?php else: ?>
    
    <h1><?= htmlspecialchars($targetForm['name']) ?></h1>
    <p>Please fill out the details below. Our team will get back to you shortly.</p>

    <form action="controllers/submit_dynamic_form.php" method="POST">
        <input type="hidden" name="form_id" value="<?= htmlspecialchars($formId) ?>">
        <input type="hidden" name="form_name" value="<?= htmlspecialchars($targetForm['name']) ?>">

        <?php foreach ($targetForm['fields'] as $field): 
            $isRequired = $field['required'] ? 'required' : '';
            $label = htmlspecialchars($field['label']);
            if($field['required']) $label .= ' <span style="color:#ef4444;">*</span>';
            $name = 'custom_fields[' . htmlspecialchars($field['name']) . ']';
            
            // Try extracting placeholder if options field acts as placeholder for text types
            $optionsRaw = $field['options'] ?? '';
            $placeholder = htmlspecialchars($field['placeholder'] ?? $optionsRaw);
        ?>
        <div class="form-group">
            <label><?= $label ?></label>

            <?php if(in_array($field['type'], ['text','email','tel','number'])): ?>
                <input type="<?= $field['type'] ?>" name="<?= $name ?>" <?= $isRequired ?> placeholder="<?= $placeholder ?>">
            
            <?php elseif($field['type'] === 'textarea'): ?>
                <textarea name="<?= $name ?>" rows="4" <?= $isRequired ?> placeholder="<?= $placeholder ?>"></textarea>
            
            <?php elseif($field['type'] === 'select'): 
                $opts = array_filter(array_map('trim', explode('|', $optionsRaw)));
            ?>
                <select name="<?= $name ?>" <?= $isRequired ?>>
                    <option value="">-- Select an option --</option>
                    <?php foreach($opts as $opt): ?>
                    <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                    <?php endforeach; ?>
                </select>
            
            <?php elseif($field['type'] === 'radio'): 
                $opts = array_filter(array_map('trim', explode('|', $optionsRaw)));
            ?>
                <div class="radio-group">
                    <?php foreach($opts as $opt): ?>
                    <label><input type="radio" name="<?= $name ?>" value="<?= htmlspecialchars($opt) ?>" <?= $isRequired ?>> <?= htmlspecialchars($opt) ?></label>
                    <?php endforeach; ?>
                </div>

            <?php elseif($field['type'] === 'checkbox'): 
                $opts = array_filter(array_map('trim', explode('|', $optionsRaw)));
            ?>
                <div class="checkbox-group">
                    <!-- Note: Array syntax for multiple checkboxes -->
                    <?php foreach($opts as $opt): ?>
                    <label><input type="checkbox" name="<?= $name ?>[]" value="<?= htmlspecialchars($opt) ?>"> <?= htmlspecialchars($opt) ?></label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>

        <button type="submit" class="btn-primary">Submit Securely</button>
    </form>
    <?php endif; ?>
</div>

</body>
</html>
