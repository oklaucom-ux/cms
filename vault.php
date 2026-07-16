<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-section active">
    <div class="section-header">
        <h2> 🔐 Personal Vault </h2>
        <div style="display:flex; gap:12px;">
            <button class="premium-btn" onclick="openPasswordModal()" style="background:linear-gradient(135deg, #6366f1, #4f46e5);">➕ Add Password</button>
            <button class="premium-btn" onclick="openTaskModal()" style="background:linear-gradient(135deg, #10b981, #059669);">➕ Add Personal Task</button>
        </div>
    </div>

    <!-- TABS -->
    <div style="display:flex; gap:8px; margin-bottom:32px; background:rgba(255,255,255,0.4); padding:6px; border-radius:12px; border:1px solid rgba(255,255,255,0.6); backdrop-filter:blur(10px); width:fit-content; box-shadow:0 4px 10px rgba(0,0,0,0.02);">
        <button id="tab-passwords" onclick="switchTab('passwords')" style="background:white; border:none; padding:10px 24px; font-weight:800; font-size:14px; cursor:pointer; color:#4f46e5; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.05); transition:all 0.2s;">Passwords</button>
        <button id="tab-tasks" onclick="switchTab('tasks')" style="background:transparent; border:none; padding:10px 24px; font-weight:700; font-size:14px; cursor:pointer; color:var(--text-muted); border-radius:8px; transition:all 0.2s;">Personal Tasks</button>
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
<div class="modal premium-modal" id="passwordModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:450px; box-shadow:0 10px 40px rgba(0,0,0,0.15); position:relative;">
        <h2 style="margin-top:0; font-size:22px; font-weight:800; margin-bottom:24px;">Save Password</h2>
        <form id="passwordForm" onsubmit="savePassword(event)">
            <input type="hidden" id="pass_id">
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Website / App Name</label>
                <input type="text" id="pass_website" required style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Username / Email</label>
                <input type="text" id="pass_username" required style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Password</label>
                <input type="password" id="pass_password" required style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
                <button type="button" onclick="generatePassword()" style="background:#f1f5f9; border:none; padding:8px 12px; border-radius:6px; margin-top:8px; cursor:pointer; font-size:12px; font-weight:700; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">🎲 Generate Strong Password</button>
            </div>
            
            <div style="margin-top:32px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" style="background:#f1f5f9; border:none; padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:700; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                <button type="submit" class="premium-btn">Save Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Task Modal -->
<div class="modal premium-modal" id="taskModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:450px; box-shadow:0 10px 40px rgba(0,0,0,0.15); position:relative;">
        <h2 style="margin-top:0; font-size:22px; font-weight:800; margin-bottom:24px;">Add Personal Task</h2>
        <form id="taskForm" onsubmit="saveTask(event)">
            <input type="hidden" id="task_id">
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Task Title</label>
                <input type="text" id="task_title" required style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Description</label>
                <textarea id="task_desc" rows="3" style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s; resize:vertical;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'"></textarea>
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Due Date & Time</label>
                <input type="datetime-local" id="task_due" style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            </div>
            
            <div style="margin-bottom:16px;">
                <label style="font-weight:700; color:var(--text-heading); margin-bottom:8px; display:block;">Remind me before due</label>
                <select id="task_reminder" style="width:100%; padding:12px 16px; border-radius:8px; border:1px solid #cbd5e1; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
                    <option value="0">No Reminder</option>
                    <option value="15">15 Minutes</option>
                    <option value="60">1 Hour</option>
                    <option value="1440">1 Day</option>
                </select>
            </div>
            
            <div style="margin-top:32px; display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="document.getElementById('taskModal').style.display='none'" style="background:#f1f5f9; border:none; padding:12px 24px; border-radius:99px; cursor:pointer; font-weight:700; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                <button type="submit" class="premium-btn" style="background:linear-gradient(135deg, #10b981, #059669);">Save Task</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Remove old modal CSS as it's inline now */
</style>

<script>
function switchTab(tab) {
    document.getElementById('view-passwords').style.display = tab === 'passwords' ? 'block' : 'none';
    document.getElementById('view-tasks').style.display = tab === 'tasks' ? 'block' : 'none';
    
    document.getElementById('tab-passwords').style.color = tab === 'passwords' ? '#4f46e5' : 'var(--text-muted)';
    document.getElementById('tab-passwords').style.background = tab === 'passwords' ? 'white' : 'transparent';
    document.getElementById('tab-passwords').style.boxShadow = tab === 'passwords' ? '0 2px 8px rgba(0,0,0,0.05)' : 'none';
    document.getElementById('tab-passwords').style.fontWeight = tab === 'passwords' ? '800' : '700';
    
    document.getElementById('tab-tasks').style.color = tab === 'tasks' ? '#10b981' : 'var(--text-muted)';
    document.getElementById('tab-tasks').style.background = tab === 'tasks' ? 'white' : 'transparent';
    document.getElementById('tab-tasks').style.boxShadow = tab === 'tasks' ? '0 2px 8px rgba(0,0,0,0.05)' : 'none';
    document.getElementById('tab-tasks').style.fontWeight = tab === 'tasks' ? '800' : '700';
}

function loadVault() {
    // Load Passwords
    fetch('controllers/vault_api.php?action=list_passwords')
    .then(r=>r.json()).then(res => {
        let h = '';
        res.data.forEach(p => {
            h += `<div class="glass-card" style="padding:24px; border-radius:16px; position:relative; display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.08)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.04)';">
                    <div>
                        <div style="font-size:32px; margin-bottom:12px; display:inline-block; background:rgba(255,255,255,0.8); padding:8px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">🌐</div>
                        <h3 style="font-size:18px; margin-bottom:4px; font-weight:800; color:var(--text-heading);">${p.website}</h3>
                        <div style="font-size:13px; color:var(--text-muted); margin-bottom:20px; font-weight:600;">${p.username}</div>
                    </div>
                    <div style="display:flex; gap:10px; border-top:1px solid rgba(0,0,0,0.05); padding-top:16px;">
                        <button id="copy-btn-${p.id}" onclick="copyPassword(this, '${p.password}')" style="background:#f1f5f9; color:#475569; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; transition:all 0.2s; flex:1;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">📋 Copy</button>
                        <button onclick="deletePassword(${p.id})" style="background:#fee2e2; color:#ef4444; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">🗑️</button>
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
            let opacity = isDone ? 0.6 : 1;
            let titleColor = isDone ? 'var(--text-muted)' : 'var(--text-heading)';
            let dueTxt = t.due_date ? new Date(t.due_date).toLocaleString() : 'No Due Date';
            let remTxt = t.reminder_minutes > 0 ? `⏰ Remind ${t.reminder_minutes}m before` : '';
            
            h += `<div class="glass-card" style="padding:24px; border-radius:16px; position:relative; opacity:${opacity}; display:flex; flex-direction:column; justify-content:space-between; transition:transform 0.2s, box-shadow 0.2s;" ${!isDone ? 'onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 12px 24px rgba(0,0,0,0.08)\';" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 10px rgba(0,0,0,0.04)\';"' : ''}>
                    <div>
                        <div style="font-size:32px; margin-bottom:12px; display:inline-block; background:rgba(255,255,255,0.8); padding:8px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05); filter:${isDone ? 'grayscale(1)' : 'none'}">📅</div>
                        <h3 style="font-size:18px; margin-bottom:6px; font-weight:800; color:${titleColor}; text-decoration:${isDone ? 'line-through' : 'none'};">${t.title}</h3>
                        <div style="font-size:13px; color:var(--text-muted); margin-bottom:16px; line-height:1.5;">${t.description || ''}</div>
                        <div style="font-size:12px; font-weight:700; color:#ef4444; margin-bottom:20px; background:rgba(239,68,68,0.1); padding:6px 10px; border-radius:6px; display:inline-block;">${dueTxt} <br> <span style="color:#f59e0b; margin-top:4px; display:block;">${remTxt}</span></div>
                    </div>
                    
                    <div style="display:flex; gap:10px; border-top:1px solid rgba(0,0,0,0.05); padding-top:16px;">
                        ${!isDone ? `<button onclick="completeTask(${t.id})" style="background:#dcfce7; color:#16a34a; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; font-weight:700; transition:all 0.2s; flex:1;" onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#dcfce7'">✔️ Mark Done</button>` : `<div style="flex:1; color:#16a34a; font-weight:700; font-size:13px; padding:8px 0; text-align:center;">Completed</div>`}
                        <button onclick="deleteTask(${t.id})" style="background:#fee2e2; color:#ef4444; border:none; padding:8px 16px; border-radius:8px; cursor:pointer; font-size:13px; transition:all 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">🗑️</button>
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

function copyPassword(btn, pass) {
    navigator.clipboard.writeText(pass);
    const originalText = btn.innerHTML;
    const originalBg = btn.style.background;
    const originalColor = btn.style.color;
    
    btn.innerHTML = '✅ Copied!';
    btn.style.background = '#dcfce7';
    btn.style.color = '#16a34a';
    
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.style.background = originalBg;
        btn.style.color = originalColor;
    }, 1500);
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
