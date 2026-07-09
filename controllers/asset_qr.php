<?php
session_start();
require_once '../includes/db.php';
requirePermission($pdo, 'manage_assets');

$id = intval($_GET['id'] ?? 0);
$all = isset($_GET['all']);

if ($all) {
    $assets = $pdo->query("SELECT * FROM assets ORDER BY asset_tag")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($id) {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id=?");
    $stmt->execute([$id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$asset) die("Asset not found");
    $assets = [$asset];
} else {
    die("No asset specified");
}

$company = $GLOBAL_SETTINGS['company_name'] ?? 'Company Management';
$baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname(dirname($_SERVER['REQUEST_URI'] ?? '')) . '/assets.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Asset QR Labels — <?= htmlspecialchars($company) ?></title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:Arial,sans-serif; }
body { background:#f3f4f6; padding:30px; }
.controls { margin-bottom:24px; display:flex; gap:12px; }
.btn { padding:10px 22px; border-radius:10px; border:none; cursor:pointer; font-size:14px; font-weight:700; }
.btn-primary { background:#6366f1; color:white; }
.btn-secondary { background:white; color:#374151; border:1px solid #d1d5db; }
.labels-grid { display:flex; flex-wrap:wrap; gap:16px; }
.label-card { background:white; border:1px solid #d1d5db; border-radius:8px; padding:16px; width:200px; text-align:center; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.label-tag { font-size:11px; font-weight:800; color:#111827; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; font-family:monospace; background:#f3f4f6; padding:3px 8px; border-radius:4px; display:inline-block; }
.label-name { font-size:12px; color:#374151; font-weight:600; margin:8px 0 4px; line-height:1.3; }
.label-type { font-size:10px; color:#9ca3af; margin-bottom:8px; }
.label-company { font-size:9px; color:#9ca3af; margin-top:8px; text-transform:uppercase; letter-spacing:.05em; }
.qr-wrap { display:flex; justify-content:center; margin:8px 0; }
.assigned { font-size:10px; color:#6366f1; font-weight:600; }
@media print {
    body { background:white; padding:10px; }
    .controls { display:none; }
    .label-card { break-inside:avoid; box-shadow:none; border:1px solid #999; }
}
</style>
</head>
<body>
<div class="controls no-print">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print All Labels</button>
    <button class="btn btn-secondary" onclick="window.history.back()">← Back to Assets</button>
    <span style="line-height:40px;font-size:13px;color:#6b7280;"><?= count($assets) ?> label(s)</span>
</div>
<div class="labels-grid" id="labelsGrid">
    <?php foreach($assets as $a): 
        $qrData = $baseUrl . '?highlight=' . urlencode($a['asset_tag']);
    ?>
    <div class="label-card">
        <div class="label-tag"><?= htmlspecialchars($a['asset_tag']) ?></div>
        <div class="qr-wrap">
            <div id="qr_<?= $a['id'] ?>" style="width:120px;height:120px;"></div>
        </div>
        <div class="label-name"><?= htmlspecialchars(substr($a['name'],0,40)) ?></div>
        <div class="label-type"><?= htmlspecialchars($a['type']) ?></div>
        <?php if($a['assigned_to']): ?>
        <div class="assigned">👤 <?= htmlspecialchars($a['assigned_to']) ?></div>
        <?php endif; ?>
        <div class="label-company"><?= htmlspecialchars($company) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<script>
const assets = <?= json_encode(array_map(fn($a) => ['id'=>$a['id'],'tag'=>$a['asset_tag']], $assets)) ?>;
const baseUrl = <?= json_encode($baseUrl) ?>;
assets.forEach(a => {
    new QRCode(document.getElementById('qr_' + a.id), {
        text: baseUrl + '?highlight=' + encodeURIComponent(a.tag),
        width: 120, height: 120,
        colorDark: '#111827', colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
</body>
</html>
