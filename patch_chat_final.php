<?php
$content = file_get_contents('chat.php');

// Add assignable_employees at the top
if (strpos($content, '$assignable_employees') === false) {
    $insert = <<<'EOF'
$assignable_employees = [];
if (in_array($_SESSION['role'], ['Admin', 'Super Admin', 'System Admin'])) {
    $assignable_employees = $pdo->query("SELECT login_id, name FROM users WHERE role NOT IN ('Client') AND status = 'Active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
EOF;
    $content = preg_replace('/(\$client_users = \$client_stmt->fetchAll\(PDO::FETCH_ASSOC\);)/', "$1\n\n$insert", $content);
}

// Replace header with assign support button
$header_find = '<div class="chat-header" id="chatHeader">';
$header_replace = <<<'EOF'
<div class="chat-header" id="chatHeader" style="justify-content:space-between;">
    <div style="display:flex; align-items:center; gap:12px;">
EOF;
if (strpos($content, 'assignSupportBtn') === false) {
    $content = str_replace($header_find, $header_replace, $content);
    $content = preg_replace('/(<div id="chatHeaderName".*?<\/div>\s*<div.*?<\/div>\s*<\/div>)/s', "$1\n    </div>\n    <?php if (in_array(\$_SESSION['role'], ['Admin', 'Super Admin', 'System Admin'])): ?>\n    <button id=\"assignSupportBtn\" onclick=\"document.getElementById('assignModal').style.display='flex'\" style=\"display:none; padding:8px 16px; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; font-weight:600; color:#475569; cursor:pointer; align-items:center; gap:6px;\">\n        <svg width=\"14\" height=\"14\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2\"></path><circle cx=\"8.5\" cy=\"7\" r=\"4\"></circle><line x1=\"20\" y1=\"8\" x2=\"20\" y2=\"14\"></line><line x1=\"23\" y1=\"11\" x2=\"17\" y2=\"11\"></line></svg>\n        Assign Support\n    </button>\n    <?php endif; ?>", $content);
}

// Add the modal at the bottom
if (strpos($content, 'assignModal') === false) {
    $modal = <<<'EOF'
<!-- Assign Support Modal -->
<div id="assignModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100; align-items:center; justify-content:center;">
    <div style="background:#fff; padding:24px; border-radius:16px; width:400px; max-width:90%;">
        <h3 style="margin-top:0; font-size:18px;">Assign Employee to Client</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">Select an employee to grant them access to chat with this client.</p>
        <form onsubmit="assignEmployee(event)">
            <input type="hidden" id="assignClientId" name="client_id">
            <div style="margin-bottom:16px;">
                <label style="font-size:13px; font-weight:600; display:block; margin-bottom:8px;">Select Employee</label>
                <select name="employee_id" id="assignEmployeeId" style="width:100%; padding:10px; border-radius:8px; border:1px solid #e2e8f0;" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach ($assignable_employees as $emp): ?>
                        <option value="<?= htmlspecialchars($emp['login_id']) ?>"><?= htmlspecialchars($emp['name']) ?> (@<?= htmlspecialchars($emp['login_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="document.getElementById('assignModal').style.display='none'" style="padding:10px 16px; background:#f1f5f9; border:none; border-radius:8px; cursor:pointer;">Cancel</button>
                <button type="submit" style="padding:10px 16px; background:var(--primary-color); color:#fff; border:none; border-radius:8px; cursor:pointer;">Assign Employee</button>
            </div>
        </form>
    </div>
</div>

<script>
function assignEmployee(e) {
    e.preventDefault();
    const clientId = document.getElementById('assignClientId').value;
    const empId = document.getElementById('assignEmployeeId').value;
    
    let fd = new FormData();
    fd.append('action', 'assign_employee');
    fd.append('client_id', clientId);
    fd.append('employee_id', empId);
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if (res.status === 'success') {
            alert('Employee assigned successfully!');
            document.getElementById('assignModal').style.display = 'none';
        } else {
            alert(res.message || 'Error assigning employee');
        }
    });
}
</script>
EOF;
    $content = str_replace('</body>', $modal . "\n</body>", $content);
}

// Modify selectUser function
$selectUser_find = <<<'EOF'
function selectUser(event, loginId, name) {
    currentChatUser = loginId;
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatArea').style.display = 'flex';
EOF;

$selectUser_replace = <<<'EOF'
function selectUser(event, loginId, name) {
    currentChatUser = loginId;
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatArea').style.display = 'flex';
    
    const btn = document.getElementById('assignSupportBtn');
    if (btn) {
        // Only show for clients
        const isClient = event.currentTarget.querySelector('.avatar-user').getAttribute('data-is-client') === 'true';
        if (isClient && !loginId.startsWith('#')) {
            btn.style.display = 'flex';
            document.getElementById('assignClientId').value = loginId;
        } else {
            btn.style.display = 'none';
        }
    }
EOF;
$content = str_replace($selectUser_find, $selectUser_replace, $content);

file_put_contents('chat.php', $content);
echo "Final patch applied.";
