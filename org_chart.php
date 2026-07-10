<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_users');

$users = $pdo->query("SELECT login_id, name, role, manager_id FROM users WHERE status = 'Active'")->fetchAll(PDO::FETCH_ASSOC);

// Build adjacency list
$tree = [];
$lookup = [];
foreach ($users as $u) {
    $lookup[$u['login_id']] = $u;
    $lookup[$u['login_id']]['children'] = [];
}

$roots = [];
foreach ($lookup as $id => &$u) {
    if (empty($u['manager_id']) || !isset($lookup[$u['manager_id']])) {
        $roots[] = &$u;
    } else {
        $lookup[$u['manager_id']]['children'][] = &$u;
    }
}
unset($u);

function renderTree($nodes) {
    if (empty($nodes)) return '';
    $html = '<ul>';
    foreach ($nodes as $node) {
        $roleColor = $node['role'] === 'Admin' ? '#ef4444' : ($node['role'] === 'Manager' ? '#3b82f6' : '#10b981');
        $html .= '<li>';
        $html .= '<div class="org-card">';
        $html .= '<div class="org-name">'.htmlspecialchars($node['name']).'</div>';
        $html .= '<div class="org-role" style="color:'.$roleColor.'">'.htmlspecialchars($node['role']).'</div>';
        $html .= '</div>';
        $html .= renderTree($node['children']);
        $html .= '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>

<style>
/* CSS Org Chart Tree */
.org-tree ul {
    padding-top: 20px; position: relative;
    transition: all 0.5s;
    display: flex; justify-content: center;
}
.org-tree li {
    float: left; text-align: center;
    list-style-type: none;
    position: relative;
    padding: 20px 5px 0 5px;
    transition: all 0.5s;
}
/* Connectors */
.org-tree li::before, .org-tree li::after{
    content: '';
    position: absolute; top: 0; right: 50%;
    border-top: 2px solid #cbd5e1;
    width: 50%; height: 20px;
}
.org-tree li::after{
    right: auto; left: 50%;
    border-left: 2px solid #cbd5e1;
}
.org-tree li:only-child::after, .org-tree li:only-child::before {
    display: none;
}
.org-tree li:only-child{ padding-top: 0;}
.org-tree li:first-child::before, .org-tree li:last-child::after{
    border: 0 none;
}
.org-tree li:last-child::before{
    border-right: 2px solid #cbd5e1;
    border-radius: 0 5px 0 0;
}
.org-tree li:first-child::after{
    border-radius: 5px 0 0 0;
}
/* Downward connectors */
.org-tree ul ul::before{
    content: '';
    position: absolute; top: 0; left: 50%;
    border-left: 2px solid #cbd5e1;
    width: 0; height: 20px;
}
/* Cards */
.org-card {
    background: white;
    border: 1px solid #cbd5e1;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    display: inline-block;
    min-width: 150px;
    position: relative;
    z-index: 10;
}
.org-name {
    font-weight: 700;
    color: #1e293b;
    font-size: 14px;
    margin-bottom: 5px;
}
.org-role {
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}
</style>

<div class="content-section active">
    <div class="section-header">
        <h2>🏢 Organizational Hierarchy</h2>
        <p style="color:var(--text-muted);">Drag to pan, scroll to zoom. Visual mapping of reporting lines.</p>
    </div>

    <div id="orgChartContainer" style="background:#f8fafc; overflow:hidden; position:relative; border-radius:12px; border:1px solid #e2e8f0; height:70vh; cursor:grab;">
        <div id="orgChartScale" style="transform-origin:0 0; transition:transform 0.1s; padding:40px; display:inline-block; min-width:100%;">
            <div class="org-tree">
                <?= renderTree($roots) ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('orgChartContainer');
    const scaleEl = document.getElementById('orgChartScale');
    
    let isDown = false;
    let startX, startY, scrollLeft, scrollTop;
    let scale = 1;
    let maxScale = 2, minScale = 0.3;

    // Panning
    container.addEventListener('mousedown', (e) => {
        isDown = true;
        container.style.cursor = 'grabbing';
        startX = e.pageX - container.offsetLeft;
        startY = e.pageY - container.offsetTop;
        scrollLeft = container.scrollLeft;
        scrollTop = container.scrollTop;
    });
    
    container.addEventListener('mouseleave', () => {
        isDown = false;
        container.style.cursor = 'grab';
    });
    
    container.addEventListener('mouseup', () => {
        isDown = false;
        container.style.cursor = 'grab';
    });
    
    container.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - container.offsetLeft;
        const y = e.pageY - container.offsetTop;
        const walkX = (x - startX) * 2;
        const walkY = (y - startY) * 2;
        container.scrollLeft = scrollLeft - walkX;
        container.scrollTop = scrollTop - walkY;
    });

    // Zooming
    container.addEventListener('wheel', (e) => {
        e.preventDefault();
        if (e.deltaY < 0) {
            scale = Math.min(scale + 0.1, maxScale);
        } else {
            scale = Math.max(scale - 0.1, minScale);
        }
        scaleEl.style.transform = `scale(${scale})`;
    });
    
    // Center initially
    container.scrollLeft = (scaleEl.scrollWidth - container.clientWidth) / 2;
});
</script>

<?php require_once 'includes/footer.php'; ?>
