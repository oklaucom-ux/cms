<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_id']) && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'index.php') {
    header("Location: login.php");
    exit();
}

// ── Onboarding Lockout Protocol ──
if (isset($_SESSION['status']) && $_SESSION['status'] === 'Pending_Docs') {
    $cFile = basename($_SERVER['PHP_SELF']);
    $allowed = ['onboarding_portal.php', 'logout.php', 'upload_user_file.php', 'get_user_files.php', 'delete_user_file.php'];
    if (!in_array($cFile, $allowed)) {
        header("Location: onboarding_portal.php");
        exit();
    }
}

// ── Global Module Access Control ──
$moduleMappings = [
    'module_crm' => ['crm.php', 'vendor_crm.php', 'update_lead_stage.php'],
    'module_projects' => ['projects.php', 'tasks.php', 'kanban.php', 'gantt.php', 'timesheets.php', 'save_project.php', 'save_task.php'],
    'module_finance' => ['invoices.php', 'procurement.php', 'expenses.php'],
    'module_hr' => ['attendance.php', 'attendance_analytics.php', 'leaves.php', 'payroll.php', 'recruitment.php', 'onboarding.php', 'org_chart.php', 'hr_interviews.php', 'performance_reviews.php'],
    'module_communication' => ['chat.php', 'intranet.php', 'pulse_surveys.php'],
    'module_assets' => ['assets.php', 'save_asset.php', 'delete_asset.php'],
    'module_support' => ['helpdesk.php', 'training.php', 'desk.php', 'kb.php', 'kpi.php', 'forms.php', 'form_analytics.php', 'feedback.php'],
    'module_workspace' => ['documents.php', 'office.php', 'calendar.php', 'vault.php', 'room_booking.php', 'reports.php', 'audit_trail.php']
];

$cFile = basename($_SERVER['PHP_SELF']);
foreach ($moduleMappings as $modKey => $files) {
    if (in_array($cFile, $files)) {
        if (($GLOBAL_SETTINGS[$modKey] ?? 'true') === 'false') {
            die("<div style='font-family:sans-serif;text-align:center;margin-top:100px;color:#111827;'>
                    <h2 style='color:#dc2626;'>🚫 Module Disabled</h2>
                    <p>The system administrator has currently disabled this module.</p>
                    <a href='dashboard.php' style='display:inline-block;margin-top:15px;padding:10px 20px;background:#4f46e5;color:white;text-decoration:none;border-radius:6px;font-weight:bold;'>Return to Dashboard</a>
                 </div>");
        }
    }
}
require_once __DIR__ . '/notifications.php';
$_notifCount = isset($_SESSION['login_id']) ? getUnreadCountDirect($pdo, $_SESSION['login_id']) : 0;

try { $pdo->exec("CREATE TABLE IF NOT EXISTS unified_tickets (id INTEGER PRIMARY KEY AUTO_INCREMENT, source VARCHAR(255) NOT NULL, ticket_number VARCHAR(255), requester_id VARCHAR(255), requester_name VARCHAR(255), department VARCHAR(255), subject TEXT NOT NULL, description TEXT NOT NULL, priority VARCHAR(255) DEFAULT 'Medium', status VARCHAR(255) DEFAULT 'Open', assigned_agent_id VARCHAR(255), resolution_notes TEXT, is_anonymous INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN task_id VARCHAR(255)"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN name TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN description TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN assigned_to TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN due_date TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN priority TEXT"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN dependency_id INTEGER"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN is_milestone INTEGER DEFAULT 0"); } catch(Exception $e){}
try { $pdo->exec("ALTER TABLE tasks ADD COLUMN created_by TEXT"); } catch(Exception $e){}

// CRM Follow Up Hook
if (isset($_SESSION['login_id'])) {
    $owner = $_SESSION['login_id'];
    $today = date('Y-m-d');
    try {
        $stmt = $pdo->prepare("SELECT id, lead_name FROM crm_leads WHERE owner_id=? AND stage NOT IN ('Won','Lost') AND follow_up_date <= ?");
        $stmt->execute([$owner, $today]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $msg = "Follow up required for {$l['lead_name']}";
            // Check if unread notification already exists to prevent spam
            $ext = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND body=? AND is_read=0");
            $ext->execute([$owner, $msg]);
            if (!$ext->fetchColumn()) {
                createNotification($pdo, $owner, "CRM Reminder", $msg, "crm.php");
                $_notifCount++; // update local count dynamically for UI
            }
        }
    } catch(Exception $e) {}

    // Self Task Reminder Hook
    // Keeps reminding the user of tasks they created that are due or overdue, unless completed/deleted.
    try {
        $taskStmt = $pdo->prepare("SELECT task_id, name FROM tasks WHERE created_by=? AND status NOT IN ('Completed', 'Deleted') AND due_date <= ?");
        $taskStmt->execute([$owner, $today]);
        foreach ($taskStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $msg = "Action Required: Your task '{$t['name']}' is due!";
            $ext = $pdo->prepare("SELECT id FROM notifications WHERE user_id=? AND body=? AND is_read=0");
            $ext->execute([$owner, $msg]);
            if (!$ext->fetchColumn()) {
                createNotification($pdo, $owner, "Self Task Reminder", $msg, "tasks.php");
                $_notifCount++;
            }
        }
    } catch(PDOException $e) {} // fail silently if created_by column missing in older envs
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title>Enterprise Management System</title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Cyno ERP">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
    #globalSearchWrap { position:relative; }
    #globalSearchBox { width:240px; padding:7px 14px 7px 36px; border-radius:99px; border:1px solid var(--input-border); background:var(--input-bg); color:var(--text-body); font-size:13px; outline:none; transition:width .25s,border-color .15s; font-family:inherit; }
    #globalSearchBox:focus { width:300px; border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(79,70,229,.1); }
    #searchIcon { position:absolute; left:11px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; pointer-events:none; }
    #searchResults { display:none; position:absolute; top:42px; left:0; width:360px; background:var(--bg-card); border:1px solid var(--border-card); border-radius:var(--radius-lg); box-shadow:var(--shadow-soft); z-index:999; max-height:380px; overflow-y:auto; }
    .sr-section { padding:10px 14px 4px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); }
    .sr-item { display:flex; align-items:center; gap:10px; padding:9px 14px; cursor:pointer; transition:background .12s; font-size:13px; color:var(--text-body); }
    .sr-item:hover { background:var(--bg-hover); }
    .sr-icon { width:26px; height:26px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; }
    .sr-meta { font-size:11px; color:var(--text-muted); }
    .notif-wrap { position:relative; cursor:pointer; }
    .notif-bell { width:34px; height:34px; border-radius:50%; background:var(--bg-hover); border:1px solid var(--border-card); display:flex; align-items:center; justify-content:center; font-size:15px; transition:all .15s; }
    .notif-bell:hover { border-color:var(--primary-color); }
    .notif-badge { position:absolute; top:-3px; right:-3px; min-width:16px; height:16px; background:var(--danger); color:#fff; border-radius:99px; font-size:9px; font-weight:700; display:flex; align-items:center; justify-content:center; border:2px solid var(--bg-header); }
    #notifDropdown { display:none; position:absolute; top:42px; right:0; width:340px; background:var(--bg-card); border:1px solid var(--border-card); border-radius:var(--radius-lg); box-shadow:var(--shadow-soft); z-index:999; max-height:460px; overflow:hidden; }
    .notif-header { padding:12px 16px; font-weight:600; font-size:13px; border-bottom:1px solid var(--border-card); display:flex; justify-content:space-between; align-items:center; color:var(--text-heading); }
    .notif-list { max-height:340px; overflow-y:auto; }
    .notif-item { padding:11px 16px; border-bottom:1px solid var(--border-card); cursor:pointer; transition:background .12s; }
    .notif-item:hover { background:var(--bg-hover); }
    .notif-item.unread {  }
    .notif-title { font-size:13px; font-weight:600; color:var(--text-heading); margin-bottom:2px; }
    .notif-body { font-size:12px; color:var(--text-muted); }
    .notif-time { font-size:11px; color:var(--text-muted); margin-top:3px; }
    .notif-empty { padding:28px; text-align:center; color:var(--text-muted); font-size:13px; }
    #sessionWarning { display:none; position:fixed; bottom:20px; right:20px; background:#1e293b; color:#e2e8f0; padding:14px 18px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.3); z-index:9999; font-size:13px; max-width:300px; }
    #sessionWarning strong { display:block; margin-bottom:5px; font-size:14px; color:#f8fafc; }
    #sessionWarning button { margin-top:8px; padding:6px 14px; border-radius:6px; border:none; cursor:pointer; font-weight:600; font-size:12px; }
    #stayBtn { background:var(--primary-color); color:#fff; margin-right:6px; }
    #logoutBtn { background:var(--danger); color:#fff; }
    </style>
    <script>
      // Load theme: server preference takes priority, then localStorage fallback
      const serverTheme = '<?= $_SESSION['preferred_theme'] ?? '' ?>';
      const localTheme = localStorage.getItem('theme');
      const activeTheme = serverTheme || localTheme || 'light';
      if (activeTheme === 'dark') document.documentElement.setAttribute('data-theme','dark');
      if (serverTheme && serverTheme !== localTheme) localStorage.setItem('theme', serverTheme);
      document.addEventListener('DOMContentLoaded',()=>{
        const token=document.querySelector('meta[name="csrf-token"]')?.content;
        if(token) {
            document.querySelectorAll('form[method="POST"]').forEach(form=>{
              let inp=document.createElement('input'); inp.type='hidden'; inp.name='csrf_token'; inp.value=token; form.prepend(inp);
            });
            
            // Global fetch interceptor for FormData
            const originalFetch = window.fetch;
            window.fetch = function(...args) {
                if (args[1] && args[1].body && args[1].body instanceof FormData) {
                    if (!args[1].body.has('csrf_token')) {
                        args[1].body.append('csrf_token', token);
                    }
                }
                return originalFetch.apply(this, args);
            };
        }
      });
      if('serviceWorker' in navigator) navigator.serviceWorker.register('/service-worker.js').catch(()=>{});
    </script>
</head>
<body>

    <div class="header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('open');" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <h1><?= htmlspecialchars($GLOBAL_SETTINGS['company_name'] ?? 'Cyno Management System') ?></h1>
        </div>
        
        <div style="display:flex;align-items:center;gap:16px;">
            <!-- Global Search -->
            <?php if(isset($_SESSION['login_id'])): ?>
            <div id="globalSearchWrap">
                <span id="searchIcon">🔍</span>
                <input type="text" id="globalSearchBox" placeholder="Search anything..." autocomplete="off" oninput="globalSearch(this.value)" onblur="setTimeout(()=>document.getElementById('searchResults').style.display='none',200)">
                <div id="searchResults"></div>
            </div>
            <?php endif; ?>

            <!-- Notification Bell -->
            <?php if(isset($_SESSION['login_id'])): ?>
            <div class="notif-wrap" onclick="toggleNotifs(event)">
                <div class="notif-bell">🔔</div>
                <?php if($_notifCount > 0): ?>
                <div class="notif-badge" id="notifBadge"><?= $_notifCount > 99 ? '99+' : $_notifCount ?></div>
                <?php else: ?>
                <div class="notif-badge" id="notifBadge" style="display:none">0</div>
                <?php endif; ?>
                <div id="notifDropdown">
                    <div class="notif-header">
                        <span>Notifications</span>
                        <button onclick="markAllRead(event)" style="background:none;border:none;color:var(--primary-color);font-size:12px;font-weight:600;cursor:pointer;">Mark all read</button>
                    </div>
                    <div class="notif-list" id="notifList"><div class="notif-empty">Loading...</div></div>
                </div>
            </div>
            <?php endif; ?>

            <div class="user-profile">
                <button id="pwaInstallBtn" class="add-button" style="display:none; background:#ec4899; height:34px; font-size:12px; margin-right:8px;">📱 Install App</button>
                <button class="theme-toggle" onclick="toggleTheme()">🌗 Theme</button>

                <button class="logout-button" onclick="window.location.href='logout.php'">Logout</button>
            </div>
        </div>
    </div>

    <!-- Session Timeout Warning -->
    <div id="sessionWarning">
        <strong>⏱ Session Expiring Soon</strong>
        Your session will expire in <span id="countdownTimer">5:00</span> due to inactivity.
        <br>
        <button id="stayBtn" onclick="extendSession()">Stay Logged In</button>
        <button id="logoutBtn" onclick="window.location.href='logout.php'">Logout Now</button>
    </div>

    <script>
    function toggleTheme(){
        const isDark=document.documentElement.getAttribute('data-theme')==='dark';
        const newTheme = isDark ? 'light' : 'dark';
        if(isDark){ document.documentElement.removeAttribute('data-theme'); }
        else { document.documentElement.setAttribute('data-theme','dark'); }
        localStorage.setItem('theme', newTheme);
        // Persist to DB
        const fd = new FormData(); fd.append('theme', newTheme); fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content||'');
        fetch('controllers/save_theme.php', {method:'POST', body:fd}).catch(()=>{});
    }

    // ── Global Search ─────────────────────────────────────────────────
    let searchTimeout;
    function globalSearch(q){
        clearTimeout(searchTimeout);
        const box=document.getElementById('searchResults');
        if(q.length<2){ box.style.display='none'; return; }
        searchTimeout=setTimeout(()=>{
            fetch('controllers/search_api.php?q='+encodeURIComponent(q)+'&csrf_token='+encodeURIComponent(document.querySelector('meta[name="csrf-token"]').content))
            .then(r=>r.json()).then(data=>renderSearchResults(data,box));
        },250);
    }
    function renderSearchResults(data,box){
        if(!data||data.total===0){ box.innerHTML='<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:14px;">No results found</div>'; box.style.display='block'; return; }
        let html='';
        const sections=[
            {key:'tasks',label:'Tasks',icon:'✅',bg:'#e0e7ff',link:'tasks.php'},
            {key:'users',label:'Users',icon:'👤',bg:'#dcfce7',link:'users.php'},
            {key:'projects',label:'Projects',icon:'🚀',bg:'#fef3c7',link:'projects.php'},
            {key:'leads',label:'CRM Leads',icon:'🎯',bg:'#fee2e2',link:'crm.php'},
            {key:'assets',label:'Assets',icon:'🖥️',bg:'#f3e8ff',link:'assets.php'},
            {key:'documents',label:'Documents',icon:'📂',bg:'#e0f2fe',link:'documents.php'},
        ];
        sections.forEach(s=>{
            if(data[s.key]&&data[s.key].length){
                html+=`<div class="sr-section">${s.label}</div>`;
                data[s.key].forEach(r=>{
                    html+=`<div class="sr-item" onclick="window.location.href='${s.link}'"><div class="sr-icon" style="background:${s.bg}">${s.icon}</div><div><div>${r.title}</div><div class="sr-meta">${r.meta||''}</div></div></div>`;
                });
            }
        });
        box.innerHTML=html; box.style.display='block';
    }

    // ── Notifications ─────────────────────────────────────────────────
    function toggleNotifs(e){
        if (e) e.stopPropagation();
        const d=document.getElementById('notifDropdown');
        if(d.style.display==='block'){ d.style.display='none'; return; }
        d.style.display='block';
        fetch('controllers/notifications_api.php?action=list')
        .then(r=>r.json()).then(data=>{
            const list=document.getElementById('notifList');
            if(!data.length){ list.innerHTML='<div class="notif-empty">All caught up! 🎉</div>'; return; }
            list.innerHTML=data.map(n=>`
                <div class="notif-item ${n.is_read?'':'unread'}" onclick="goNotif(${n.id},'${n.link}')">
                    <div class="notif-title">${n.title}</div>
                    <div class="notif-body">${n.body||''}</div>
                    <div class="notif-time">${n.ago}</div>
                </div>`).join('');
        });
    }
    function goNotif(id,link){
        fetch('controllers/notifications_api.php?action=read&id='+id);
        const badge=document.getElementById('notifBadge');
        const current=parseInt(badge.textContent)||0;
        if(current>1){ badge.textContent=current-1; } else { badge.style.display='none'; }
        if(link) window.location.href=link;
        document.getElementById('notifDropdown').style.display='none';
    }
    function markAllRead(e){
        e.stopPropagation();
        fetch('controllers/notifications_api.php?action=read_all');
        document.getElementById('notifList').innerHTML='<div class="notif-empty">All caught up! 🎉</div>';
        document.getElementById('notifBadge').style.display='none';
    }
    document.addEventListener('click',e=>{ if(!e.target.closest('.notif-wrap')) document.getElementById('notifDropdown').style.display='none'; });

    // ── Session timeout warning (warn at 25 min, auto-logout at 30) ───
    let sessionWarned=false, sessionCountdown;
    const SESSION_TIMEOUT=30*60, WARN_BEFORE=5*60;
    let lastActivity=Date.now();
    ['mousemove','keydown','click','scroll'].forEach(ev=>document.addEventListener(ev,()=>lastActivity=Date.now(),{passive:true}));
    setInterval(()=>{
        const idle=(Date.now()-lastActivity)/1000;
        const remaining=SESSION_TIMEOUT-idle;
        if(remaining<=0){ window.location.href='logout.php?timeout=1'; return; }
        if(remaining<=WARN_BEFORE && !sessionWarned){
            sessionWarned=true;
            document.getElementById('sessionWarning').style.display='block';
            startCountdown(remaining);
        }
        if(remaining>WARN_BEFORE && sessionWarned){ sessionWarned=false; document.getElementById('sessionWarning').style.display='none'; }
    },5000);
    function startCountdown(sec){
        let s=Math.floor(sec);
        clearInterval(sessionCountdown);
        sessionCountdown=setInterval(()=>{
            s--;
            const m=Math.floor(s/60), ss=s%60;
            document.getElementById('countdownTimer').textContent=m+':'+(ss<10?'0':'')+ss;
            if(s<=0){ clearInterval(sessionCountdown); window.location.href='logout.php?timeout=1'; }
        },1000);
    }
    function extendSession(){
        fetch('controllers/heartbeat.php');
        lastActivity=Date.now(); sessionWarned=false;
        document.getElementById('sessionWarning').style.display='none';
        clearInterval(sessionCountdown);
    }

    // ── PWA Installation Logic ────────────────────────────────────────
    let deferredPrompt;
    const installBtn = document.getElementById('pwaInstallBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        // Update UI notify the user they can install the PWA
        installBtn.style.display = 'inline-block';
    });

    installBtn.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        // Show the install prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
            installBtn.style.display = 'none';
        }
        deferredPrompt = null;
    });

    window.addEventListener('appinstalled', () => {
        // Hide the install button after successful installation
        installBtn.style.display = 'none';
        deferredPrompt = null;
    });
    </script>
