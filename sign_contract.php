<?php
require_once 'includes/db.php';

$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE token = ?");
$stmt->execute([$token]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>Invalid or Expired Link</h2></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Contract: <?= htmlspecialchars($contract['title']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        h1 { margin-top: 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .contract-content { padding: 20px; background: #f1f5f9; border-radius: 8px; font-family: monospace; white-space: pre-wrap; margin-bottom: 30px; font-size: 14px; max-height: 400px; overflow-y: auto; }
        .signature-pad { border: 2px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; cursor: crosshair; }
        .btn { background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 16px; width: 100%; margin-top: 20px; }
        .success-box { background: #d1fae5; color: #065f46; padding: 20px; border-radius: 8px; text-align: center; border: 1px solid #34d399; }
    </style>
</head>
<body>

<div class="container">
    <?php if(isset($_GET['success'])): ?>
        <div class="success-box">
            <h2>✅ Contract Signed Successfully</h2>
            <p>Thank you. Your digital signature has been recorded and cryptographically stamped.</p>
        </div>
    <?php else: ?>
        <h1><?= htmlspecialchars($contract['title']) ?></h1>
        <p><strong>Prepared for:</strong> <?= htmlspecialchars($contract['recipient_name']) ?> (<?= htmlspecialchars($contract['recipient_email']) ?>)</p>
        
        <div class="contract-content"><?= htmlspecialchars($contract['content_html']) ?></div>
        
        <?php if($contract['status'] === 'Signed'): ?>
            <div style="text-align:center; border:2px solid #10b981; padding:20px; border-radius:8px;">
                <h3 style="color:#10b981; margin-top:0;">Already Signed</h3>
                <p>Signed At: <?= $contract['signed_at'] ?></p>
                <img src="<?= $contract['signature_data'] ?>" style="max-height:100px; border:1px solid #e2e8f0; background:white;">
            </div>
        <?php else: ?>
            <h3>Digital Signature</h3>
            <p style="font-size:12px; color:#64748b;">Please sign inside the box below using your mouse or touchscreen.</p>
            
            <form method="POST" action="controllers/save_contract.php" id="signForm">
                <input type="hidden" name="action" value="sign">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <input type="hidden" name="signature_data" id="signatureData">
                
                <canvas id="sigCanvas" width="600" height="200" class="signature-pad" style="width:100%; max-width:600px;"></canvas>
                <br>
                <button type="button" onclick="clearCanvas()" style="background:#e2e8f0; border:none; padding:6px 12px; border-radius:4px; margin-top:10px; cursor:pointer; font-size:12px;">Clear Signature</button>
                
                <p style="font-size:12px; color:#64748b; margin-top:20px;">By clicking the button below, you agree to the terms of the contract and acknowledge this digital signature carries the same legal weight as a physical signature.</p>
                <button type="button" onclick="submitSignature()" class="btn">I Agree & Sign Document</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const canvas = document.getElementById('sigCanvas');
if (canvas) {
    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let lastX = 0;
    let lastY = 0;

    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX || e.touches[0].clientX) - rect.left,
            y: (e.clientY || e.touches[0].clientY) - rect.top
        };
    }

    function draw(e) {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getMousePos(e);
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(lastX, lastY);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        [lastX, lastY] = [pos.x, pos.y];
    }

    canvas.addEventListener('mousedown', (e) => {
        isDrawing = true;
        const pos = getMousePos(e);
        [lastX, lastY] = [pos.x, pos.y];
    });
    
    canvas.addEventListener('touchstart', (e) => {
        isDrawing = true;
        const pos = getMousePos(e);
        [lastX, lastY] = [pos.x, pos.y];
    });

    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('mouseup', () => isDrawing = false);
    canvas.addEventListener('mouseout', () => isDrawing = false);
    canvas.addEventListener('touchend', () => isDrawing = false);
}

function clearCanvas() {
    if(!canvas) return;
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function submitSignature() {
    // Check if canvas is empty (simplified check)
    const blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;
    if(canvas.toDataURL() === blank.toDataURL()) {
        alert("Please provide a signature before submitting.");
        return;
    }
    
    document.getElementById('signatureData').value = canvas.toDataURL();
    document.getElementById('signForm').submit();
}
</script>

</body>
</html>
