<?php
// includes/flash.php — Standardised flash message system for all modules
// Usage: setFlash('success', 'Saved!') then redirect; renderFlash() in view

function setFlash(string $type, string $msg): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['_flash'] = ['type' =>$type, 'msg' =>$msg];
}

function renderFlash(): void {
    if (empty($_SESSION['_flash'])) return;
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    $colors = [
        'success' => ['bg'=>'#dcfce7','border'=>'#16a34a','text'=>'#15803d','icon'=>'✅'],
        'error'   => ['bg'=>'#fee2e2','border'=>'#dc2626','text'=>'#b91c1c','icon'=>'⚠️'],
        'info'    => ['bg'=>'#dbeafe','border'=>'#2563eb','text'=>'#1d4ed8','icon'=>'ℹ️'],
        'warning' => ['bg'=>'#fef3c7','border'=>'#d97706','text'=>'#92400e','icon'=>'⚡'],
    ];
    $c = $colors[$f['type']] ?? $colors['info'];
    echo "<div style='background:{$c['bg']};border:1px solid {$c['border']};border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-weight:600;font-size:14px;color:{$c['text']};'>"
       . "<span style='font-size:16px'>{$c['icon']}</span>"
       . "<span>" . htmlspecialchars($f['msg']) . "</span>"
       . "<button onclick=\"this.parentElement.remove()\" style='margin-left:auto;background:none;border:none;cursor:pointer;font-size:18px;color:{$c['text']};opacity:.6;'>×</button>"
       . "</div>";
}
