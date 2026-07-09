<?php
// embed_form.php
require_once 'includes/db.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid Target ID");

$stmt = $pdo->prepare("SELECT * FROM dynamic_forms WHERE id = ? AND is_public = 1");
$stmt->execute([$id]);
$form = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$form) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif; color: #666;'>This form is not publicly configured for external lead capture.</div>");
}

$schema = json_decode($form['schema_json'], true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['title']) ?></title>
    <!-- Use basic isolated structural styling so it gracefully fits inside diverse external iframes -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
        body { font-family: 'Inter', sans-serif; background: transparent; margin:0; padding:15px; color:#1f2937; }
        .form-container { 
            background: #ffffff; max-width: 600px; margin: 0 auto; padding: 32px; 
            border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #f3f4f6; 
        }
        .form-container h2 { margin-top: 0; color: #111827; border-bottom: 2px solid #f3f4f6; padding-bottom: 16px; font-weight: 600; letter-spacing: -0.5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #374151; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 14px 16px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 15px;
            box-sizing: border-box; transition: all 0.2s; background: #f9fafb;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: #6366f1; background: #fff; box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
        }
        .submit-btn {
            background: #6366f1; color: white; border: none;
            padding: 14px 0; border-radius: 10px; font-size: 16px; font-weight: 600; cursor: pointer;
            width: 100%; transition: all 0.2s; box-shadow: 0 4px 6px -1px rgba(99,102,241,0.2);
        }
        .submit-btn:hover { background: #4f46e5; transform: translateY(-1px); box-shadow: 0 6px 12px rgba(99,102,241,0.3); }
        .success-box { background: rgba(16, 185, 129, 0.1); color: #059669; padding: 24px; border-radius: 12px; text-align: center; font-weight: 500; border: 1px solid rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if (isset($_GET['success'])): ?>
            <div class="success-box">
                <h3 style="margin:0 0 10px 0;">🎉 Success</h3>
                Thank you! Your information has been securely received by our system.
            </div>
        <?php else: ?>
            <h2><?= htmlspecialchars($form['title']) ?></h2>
            <form method="POST" action="controllers/submit_public_form.php">
                <input type="hidden" name="form_id" value="<?= $form['id'] ?>">
                
                <?php foreach($schema as $field): ?>
                    <div class="form-group">
                        <label><?= htmlspecialchars($field['label']) ?></label>
                        <?php if($field['type'] === 'textarea'): ?>
                            <textarea name="custom_data[<?= htmlspecialchars($field['label']) ?>]" rows="4" required></textarea>
                        <?php else: ?>
                            <input type="<?= htmlspecialchars($field['type']) ?>" name="custom_data[<?= htmlspecialchars($field['label']) ?>]" required>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit" class="submit-btn">Submit Information</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
