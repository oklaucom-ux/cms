<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-section active">
    <div class="section-header">
        <h2> 🔐 Personal Vault </h2>
        <div style="display:flex; gap:10px;">
            <button class="add-button" onclick="openPasswordModal()" style="background:#4f46e5;">➕ Add Password</button>
            <button class="add-button" onclick="openTaskModal()" style="background:#10b981;">➕ Add Personal Task</button>
        </div>
    </div>

    <!-- TABS -->
    <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
        <button id="tab-passwords" onclick="switchTab('passwords')" style="background:none; border:none; padding:10px 20px; font-weight:bold; font-size:16px; cursor:pointer; color:#4f46e5; border-bottom:3px solid #4f46e5;">Passwords</button>
        <button id="tab-tasks" onclick="switchTab('tasks')" style="background:none; border:none; padding:10px 20px; font-weight:bold; font-size:16px; cursor:pointer; color:#64748b;">Personal Tasks</button>
    </div>

    <!-- PASSWORDS VIEW -->
    <div id="view-passwords">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;" id="passwordGrid">
            <!-- Rendered via JS -->
        </div>
    </div>

    <!-- TASKS VIEW -->
    <div id="view-tasks" style="display:none;">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;" id="taskGrid">
            <!-- Rendered via JS -->
        </div>
    </div>
</div>

<!-- Password Modal -->
<div class="modal" id="passwordModal">
    <div class="modal-content">
        <h2>Save Password</h2>
        <form id="passwordForm" onsubmit="savePassword(event)">
            <input type="hidden" id="pass_id">
            <label>Website / App Name</label>
            <input type="text" id="pass_website" required>
            <label>Username / Email</label>
            <input type="text" id="pass_username" required>
            <label>Password</label>
            <input type="password" id="pass_password" required>
            <button type="button" onclick="generatePassword()" style="background:#e2e8f0; border:none; padding:8px; border-radius:4px; margin-top:5px; cursor:pointer; font-size:12px;">🎲 Generate Strong Password</button>
            
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" style="background:#ccc; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" class="add-button">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Task Modal -->
<div class="modal" id="taskModal">
    <div class="modal-content">
        <h2>Add Personal Task</h2>
        <form id="taskForm" onsubmit="saveTask(event)">
            <input type="hidden" id="task_id">
            <label>Task Title</label>
            <input type="text" id="task_title" required>
            <label>Description</label>
            <textarea id="task_desc" rows="3"></textarea>
            
            <label>Due Date & Time</label>
            <input type="datetime-local" id="task_due">
            
            <label>Remind me before due</label>
            <select id="task_reminder">
                <option value="0">No Reminder</option>
                <option value="15">15 Minutes</option>
                <option value="60">1 Hour</option>
                <option value="1440">1 Day</option>
            </select>
            
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('taskModal').style.display='none'" style="background:#ccc; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" class="add-button" style="background:#10b981;">Save Task</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; }
.modal-content { background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
.modal-content label { display:block; margin-top:15px; font-weight:bold; font-size:14px; color:#475569; }
.modal-content input, .modal-content textarea, .modal-content select { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px; outline:none; }
.modal-content input:focus, .modal-content textarea:focus { border-color:#4f46e5; }
</style>

<script>
function switchTab(tab) {
    document.getElementById('view-passwords').style.display = tab === 'passwords' ? 'block' : 'none';
    document.getElementById('view-tasks').style.display = tab === 'tasks' ? 'block' : 'none';
    
    document.getElementById('tab-passwords').style.color = tab === 'passwords' ? '#4f46e5' : '#64748b';
    document.getElementById('tab-passwords').style.borderBottom = tab === 'passwords' ? '3px solid #4f46e5' : 'none';
    
    document.getElementById('tab-tasks').style.color = tab === 'tasks' ? '#10b981' : '#64748b';
    document.getElementById('tab-tasks').style.borderBottom = tab === 'tasks' ? '3px solid #10b981' : 'none';
}

function loadVault() {
    // Load Passwords
    fetch('controllers/vault_api.php?action=list_passwords')
    .then(r=>r.json()).then(res => {
        let h = '';
        res.data.forEach(p => {
            h += `<div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02); position:relative;">
                    <div style="font-size:24px; margin-bottom:10px;">🌐</div>
                    <h3 style="font-size:18px; margin-bottom:5px; color:#0f172a;">${p.website}</h3>
                    <div style="font-size:14px; color:#64748b; margin-bottom:15px;">${p.username}</div>
                    <div style="display:flex; gap:10px;">
                        <button onclick="copyToClipboard('${p.password}')" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">📋 Copy</button>
                        <button onclick="deletePassword(${p.id})" style="background:#fee2e2; color:#ef4444; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; margin-left:auto;">🗑️</button>
                    </div>
                  </div>`;
        });
        document.getElementById('passwordGrid').innerHTML = h || '<p style="color:#94a3b8;">No passwords saved yet.</p>';
    });

    // Load Tasks
    fetch('controllers/vault_api.php?action=list_tasks')
    .then(r=>r.json()).then(res => {
        let h = '';
        res.data.forEach(t => {
            let isDone = t.status === 'Completed';
            let bg = isDone ? '#f8fafc' : 'white';
            let color = isDone ? '#94a3b8' : '#0f172a';
            let dueTxt = t.due_date ? new Date(t.due_date).toLocaleString() : 'No Due Date';
            let remTxt = t.reminder_minutes > 0 ? `⏰ Remind ${t.reminder_minutes}m before` : '';
            
            h += `<div style="background:${bg}; padding:20px; border-radius:12px; border:1px solid #e2e8f0; position:relative; opacity:${isDone ? 0.7 : 1};">
                    <h3 style="font-size:18px; margin-bottom:5px; color:${color}; text-decoration:${isDone ? 'line-through' : 'none'};">${t.title}</h3>
                    <div style="font-size:13px; color:#64748b; margin-bottom:10px;">${t.description || ''}</div>
                    <div style="font-size:12px; font-weight:bold; color:#ef4444; margin-bottom:15px;">📅 ${dueTxt} <br> <span style="color:#f59e0b;">${remTxt}</span></div>
                    
                    <div style="display:flex; gap:10px;">${!isDone ? `<button onclick="completeTask(${t.id})" style="background:#dcfce7; color:#16a34a; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px;">✔️ Mark Done</button>` : ''}
                        <button onclick="deleteTask(${t.id})" style="background:#fee2e2; color:#ef4444; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; margin-left:auto;">🗑️</button>
                    </div>
                  </div>`;
        });
        document.getElementById('taskGrid').innerHTML = h || '<p style="color:#94a3b8;">No personal tasks.</p>';
    });
}
loadVault();

function openPasswordModal() {
    document.getElementById('passwordForm').reset();
    document.getElementById('pass_id').value = '';
    document.getElementById('passwordModal').style.display = 'flex';
}

function openTaskModal() {
    document.getElementById('taskForm').reset();
    document.getElementById('task_id').value = '';
    document.getElementById('taskModal').style.display = 'flex';
}

function generatePassword() {
    let chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
    let pass = "";
    for(let i=0; i<16; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
    document.getElementById('pass_password').value = pass;
    document.getElementById('pass_password').type = 'text'; // Show it to them
    setTimeout(()=> document.getElementById('pass_password').type = 'password', 5000);
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text);
    // Visual feedback could be added here
}

function savePassword(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'save_password');
    fd.append('id', document.getElementById('pass_id').value);
    fd.append('website', document.getElementById('pass_website').value);
    fd.append('username', document.getElementById('pass_username').value);
    fd.append('password', document.getElementById('pass_password').value);
    
    fetch('controllers/vault_api.php', {method:'POST', body:fd}).then(()=> {
        document.getElementById('passwordModal').style.display = 'none';
        loadVault();
    });
}

function deletePassword(id) {
    if(!confirm('Delete this password?')) return;
    let fd = new FormData(); fd.append('action', 'delete_password'); fd.append('id', id);
    fetch('controllers/vault_api.php', {method:'POST', body:fd}).then(()=> loadVault());
}

function saveTask(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'save_task');
    fd.append('id', document.getElementById('task_id').value);
    fd.append('title', document.getElementById('task_title').value);
    fd.append('description', document.getElementById('task_desc').value);
    fd.append('due_date', document.getElementById('task_due').value);
    fd.append('reminder_minutes', document.getElementById('task_reminder').value);
    
    fetch('controllers/vault_api.php', {method:'POST', body:fd}).then(()=> {
        document.getElementById('taskModal').style.display = 'none';
        loadVault();
    });
}

function completeTask(id) {
    let fd = new FormData(); fd.append('action', 'complete_task'); fd.append('id', id);
    fetch('controllers/vault_api.php', {method:'POST', body:fd}).then(()=> loadVault());
}

function deleteTask(id) {
    if(!confirm('Delete this task?')) return;
    let fd = new FormData(); fd.append('action', 'delete_task'); fd.append('id', id);
    fetch('controllers/vault_api.php', {method:'POST', body:fd}).then(()=> loadVault());
}

// ----- BACKGROUND REMINDER DAEMON -----
// Check for reminders every 60 seconds
setInterval(() => {
    fetch('controllers/vault_api.php?action=check_reminders')
    .then(r=>r.json())
    .then(res => {
        if(res.status === 'success' && res.reminders_sent > 0) {
            // Trigger UI reload if a notification was sent so the bell updates
            if (typeof loadNotifications === 'function') loadNotifications();
        }
    });
}, 60000); // 1 min

// Initial check on page load
setTimeout(() => { fetch('controllers/vault_api.php?action=check_reminders'); }, 3000);

</script>

<?php require_once 'includes/footer.php'; ?>
