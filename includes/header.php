<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['login_id']) && basename($_SERVER['PHP_SELF']) != 'login.php' && basename($_SERVER['PHP_SELF']) != 'index.php') {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '/lang.php';

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
    'module_hr' => ['hr_dashboard.php', 'attendance.php', 'attendance_analytics.php', 'leaves.php', 'payroll.php', 'recruitment.php', 'onboarding.php', 'org_chart.php', 'hr_interviews.php', 'performance_reviews.php'],
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
    <script>
      // Theme initialization MUST happen before CSS to prevent FOUC (white flash)
      const serverTheme = '<?= $_SESSION['preferred_theme'] ?? '' ?>';
      const localTheme = localStorage.getItem('theme');
      const activeTheme = serverTheme || localTheme || 'light';
      if (activeTheme === 'dark') document.documentElement.setAttribute('data-theme','dark');
      if (serverTheme && serverTheme !== localTheme) localStorage.setItem('theme', serverTheme);
    </script>
    <style>
      :root {
          --primary-color: <?= htmlspecialchars($GLOBAL_SETTINGS['primary_color'] ?? '#4f46e5') ?> !important;
      }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?? '' ?>">
    <title>Enterprise Management System</title>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Cyno ERP">
    <link rel="apple-touch-icon" href="assets/icons/icon-192x192.png">
    <link rel="icon" href="data:,">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/simple-datatables@9.2.1/dist/style.css" rel="stylesheet" type="text/css">
    <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.2.1" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <style>
    #globalSearchWrap { position:relative; }
    #globalSearchBox { 
        width:240px; padding:8px 16px 8px 40px; border-radius:99px; 
        border:1px solid rgba(255,255,255,0.1); background: rgba(15,23,42,0.4); 
        color: #f8fafc; font-size:13px; outline:none; 
        transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-family:inherit; 
        backdrop-filter: blur(10px);
    }
    #globalSearchBox::placeholder { color: rgba(255,255,255,0.4); }
    #globalSearchBox:focus { 
        width:320px; 
        border-color: #ec4899; 
        background: rgba(15,23,42,0.8);
        box-shadow: 0 0 0 3px rgba(236,72,153,0.15), 0 4px 12px rgba(236,72,153,0.2); 
    }
    #searchIcon { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,0.5); font-size:14px; pointer-events:none; transition: color 0.3s; }
    #globalSearchBox:focus + #searchIcon { color: #ec4899; }
    
    #searchResults { display:none; position:absolute; top:48px; left:0; width:380px; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px); border:1px solid rgba(255,255,255,0.1); border-radius:16px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); z-index:999; max-height:420px; overflow-y:auto; }
    .sr-section { padding:12px 16px 6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:0.1em; color: #ec4899; }
    .sr-item { display:flex; align-items:center; gap:12px; padding:10px 16px; cursor:pointer; transition:all 0.2s; font-size:13px; color: #f8fafc; }
    .sr-item:hover { background: rgba(255,255,255,0.05); transform: translateX(4px); }
    .sr-icon { width:32px; height:32px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; background: rgba(255,255,255,0.1); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
    .sr-meta { font-size:11px; color: rgba(255,255,255,0.5); margin-top:2px; }
    
    .notif-wrap { position:relative; cursor:pointer; }
    .notif-bell { width:38px; height:38px; border-radius:50%; background: rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); display:flex; align-items:center; justify-content:center; font-size:16px; transition:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(10px); }
    .notif-bell:hover { border-color: #ec4899; background: rgba(236,72,153,0.1); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(236,72,153,0.2); }
    .notif-badge { position:absolute; top:-4px; right:-4px; min-width:18px; height:18px; background: linear-gradient(135deg, #ef4444, #f43f5e); color:#fff; border-radius:99px; font-size:9px; font-weight:800; display:flex; align-items:center; justify-content:center; box-shadow: 0 2px 6px rgba(239,68,68,0.4); }
    
    #notifDropdown { display:none; position:absolute; top:48px; right:0; width:360px; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px); border:1px solid rgba(255,255,255,0.1); border-radius:16px; box-shadow: 0 20px 40px rgba(0,0,0,0.4); z-index:999; max-height:460px; overflow:hidden; }
    .notif-header { padding:14px 18px; font-weight:700; font-size:13px; border-bottom:1px solid rgba(255,255,255,0.05); display:flex; justify-content:space-between; align-items:center; color: #f8fafc; }
    .notif-list { max-height:340px; overflow-y:auto; }
    .notif-item { padding:12px 18px; border-bottom:1px solid rgba(255,255,255,0.05); cursor:pointer; transition:all 0.2s; }
    .notif-item:hover { background: rgba(255,255,255,0.05); padding-left:22px; }
    .notif-item.unread { background: rgba(236,72,153,0.03); border-left: 3px solid #ec4899; }
    .notif-title { font-size:13.5px; font-weight:600; color: #f8fafc; margin-bottom:4px; }
    .notif-body { font-size:12px; color: rgba(255,255,255,0.6); }
    .notif-time { font-size:11px; color: #ec4899; margin-top:6px; font-weight: 500; }
    .notif-empty { padding:32px; text-align:center; color: rgba(255,255,255,0.5); font-size:13px; }
    
    #sessionWarning { display:none; position:fixed; bottom:24px; right:24px; background: rgba(15,23,42,0.9); backdrop-filter: blur(12px); border: 1px solid rgba(236,72,153,0.3); color:#f8fafc; padding:16px 20px; border-radius:16px; box-shadow: 0 10px 30px rgba(236,72,153,0.2); z-index:9999; font-size:13px; max-width:320px; }
    #sessionWarning strong { display:block; margin-bottom:6px; font-size:14px; color:#ec4899; }
    #sessionWarning button { margin-top:12px; padding:8px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; font-size:12px; transition: all 0.2s; }
    #stayBtn { background: linear-gradient(135deg, #4f46e5, #7c3aed); color:#fff; margin-right:8px; box-shadow: 0 4px 12px rgba(79,70,229,0.3); }
    #stayBtn:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(79,70,229,0.4); }
    #logoutBtn { background: transparent; border: 1px solid rgba(255,255,255,0.2); color:#fff; }
    #logoutBtn:hover { background: rgba(255,255,255,0.1); }
    
    /* Premium UI Classes */
    .glass-card { background: var(--bg-card); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--border-card); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.06); border-radius: 16px; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease; overflow: hidden; }
    [data-theme="dark"] .glass-card { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3); }
    .glass-card.hoverable:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(0, 0, 0, 0.1); border-color: rgba(79,70,229,0.3); }
    [data-theme="dark"] .glass-card.hoverable:hover { box-shadow: 0 16px 40px rgba(0, 0, 0, 0.4); border-color: rgba(236,72,153,0.3); }
    .premium-gradient-text { background: linear-gradient(135deg, #4f46e5, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800; }
    .premium-btn { background: linear-gradient(135deg, #4f46e5, #ec4899); color: white; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 13.5px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 6px 20px rgba(236, 72, 153, 0.3); display: inline-flex; align-items: center; gap: 10px; }
    .premium-btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(236, 72, 153, 0.5); filter: brightness(1.1); }
    .premium-tab { padding: 10px 18px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; border-radius: 10px; color: rgba(255,255,255,0.5); font-size: 14px; user-select: none; border-bottom: 2px solid transparent; }
    .premium-tab:hover { background: rgba(255,255,255,0.05); color: #fff; }
    .premium-tab.active { background: rgba(236,72,153,0.1); color: #ec4899; border-bottom: 2px solid #ec4899; }
    .premium-modal { backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); background: rgba(15, 23, 42, 0.7) !important; }
    .premium-modal > div { background: rgba(15, 23, 42, 0.95) !important; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.5); border-radius: 20px !important; }
    </style>
    <script>
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

        // Initialize TomSelect on all selects
        document.querySelectorAll('select:not([data-no-ts])').forEach(el => {
            new TomSelect(el, {
                create: false,
                sortField: { field: "text", direction: "asc" }
            });
        });
      });
      if('serviceWorker' in navigator) navigator.serviceWorker.register('service-worker.js').catch(()=>{});

      // Global Search Keyboard Shortcut (/)
      document.addEventListener('keydown', (e) => {
          if (e.key === '/' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
              e.preventDefault();
              document.getElementById('globalSearchBox')?.focus();
          }
      });
    </script>
</head>
<body>

    <div class="header">
        <div style="display:flex;align-items:center;gap:12px;">
            <button class="hamburger" onclick="document.querySelector('.sidebar').classList.toggle('open');document.querySelector('.sidebar-overlay').classList.toggle('open');" aria-label="Menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <?php if (!empty($GLOBAL_SETTINGS['company_logo'])): ?>
                <img src="<?= htmlspecialchars($GLOBAL_SETTINGS['company_logo']) ?>" alt="Logo" style="height:32px; object-fit:contain; border-radius:4px;">
            <?php endif; ?>
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
            <button id="pwaInstallBtn" class="add-button" style="display:none; background:#ec4899; height:34px; font-size:12px; margin-right:8px; white-space:nowrap;">📱 Install App</button>
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
                <?php if(isset($_SESSION['active_workspace_name'])): ?>
                <button class="theme-toggle" style="background:#10b981; color:#fff; font-weight:600;" onclick="window.location.href='workspaces.php'" title="Click to change workspace">
                    🏢 <?= htmlspecialchars($_SESSION['active_workspace_name']) ?>
                </button>
                <?php endif; ?>
                
                <select id="langSwitcher" onchange="changeLanguage(this.value)" style="background:var(--bg-card); color:var(--text-body); border:1px solid var(--border-card); padding:6px 12px; border-radius:8px; font-weight:600; cursor:pointer; font-size:13px; margin-right:8px; outline:none;">
                    <option value="en" <?= ($_SESSION['preferred_lang'] ?? 'en') === 'en' ? 'selected' : '' ?>>🇬🇧 EN</option>
                    <option value="hi" <?= ($_SESSION['preferred_lang'] ?? '') === 'hi' ? 'selected' : '' ?>>🇮🇳 HI</option>
                </select>

                <button class="theme-toggle" style="background:var(--primary-color); color:#fff; font-weight:600;" onclick="openProfileModal()">👤 <?= __('Profile') ?></button>
                <button class="theme-toggle" onclick="toggleTheme()">🌗 <?= __('Toggle Theme') ?></button>

                <button class="logout-button" onclick="window.location.href='logout.php'"><?= __('Logout') ?></button>
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
    function changeLanguage(lang) {
        const fd = new FormData();
        fd.append('lang', lang);
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content||'');
        fetch('controllers/save_language.php', {method:'POST', body:fd}).then(() => window.location.reload());
    }

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

    if (installBtn) {
        // Show the install button unless we are already running in standalone mode (installed app)
        const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;
        if (!isStandalone) {
            installBtn.style.display = 'inline-block';
        }

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            installBtn.style.display = 'inline-block';
        });

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                if (outcome === 'accepted') {
                    installBtn.style.display = 'none';
                }
                deferredPrompt = null;
            } else {
                Swal.fire({
                    title: 'Install Cyno ERP',
                    html: `
                        <div style="text-align: left; font-size: 14px; color: var(--text-body);">
                            <p>To install this application on your device:</p>
                            <ol style="margin-top: 10px; padding-left: 20px; line-height: 1.6;">
                                <li>Open your browser's menu (e.g. the <b>three dots icon</b> in Chrome/Edge, or the <b>Share button</b> in Safari).</li>
                                <li>Look for and click <b>"Install app"</b> or <b>"Add to Home screen"</b>.</li>
                                <li>Confirm the installation to add Cyno ERP to your desktop or mobile home screen!</li>
                            </ol>
                            <p style="margin-top: 12px; font-size: 11px; opacity: 0.8; border-top: 1px solid var(--border-card); padding-top: 8px;">
                                <i>Note: PWAs require a secure connection (HTTPS) to enable direct installation.</i>
                            </p>
                        </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'Got it',
                    confirmButtonColor: '#4f46e5'
                });
            }
        });

        window.addEventListener('appinstalled', () => {
            installBtn.style.display = 'none';
            deferredPrompt = null;
        });
    }
    </script>

    <!-- My Profile Modal -->
    <div id="profileModal" class="modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:var(--bg-card); border-radius:20px; padding:30px; width:450px; max-width:90vw; box-shadow:0 25px 60px rgba(0,0,0,0.3); position:relative;">
            <button onclick="document.getElementById('profileModal').style.display='none'" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:24px; cursor:pointer; color:var(--text-muted);">&times;</button>
            <h2 style="color:var(--text-heading); margin-bottom:15px; font-size:20px; font-weight:700;">👤 My Profile</h2>
            
            <div style="margin-bottom:20px; border-bottom:1px solid var(--border-card); padding-bottom:15px; font-size:13.5px;">
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px; margin-bottom:8px;">
                    <span style="color:var(--text-muted);">Name:</span>
                    <strong id="profileModalName" style="color:var(--text-body);"></strong>
                </div>
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px; margin-bottom:8px;">
                    <span style="color:var(--text-muted);">Login ID:</span>
                    <span id="profileModalLoginId" style="color:var(--text-body);"></span>
                </div>
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px; margin-bottom:8px;">
                    <span style="color:var(--text-muted);">Email:</span>
                    <span id="profileModalEmail" style="color:var(--text-body);"></span>
                </div>
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px; margin-bottom:8px;">
                    <span style="color:var(--text-muted);">Role:</span>
                    <strong id="profileModalRole" style="color:var(--primary-color);"></strong>
                </div>
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px; margin-bottom:8px;">
                    <span style="color:var(--text-muted);">Department:</span>
                    <span id="profileModalDepartment" style="color:var(--text-body);"></span>
                </div>
                <div style="display:grid; grid-template-columns:100px 1fr; gap:8px;">
                    <span style="color:var(--text-muted);">Branch:</span>
                    <span id="profileModalBranch" style="color:var(--text-body);"></span>
                </div>
            </div>

            <h3 style="color:var(--text-heading); margin-bottom:12px; font-size:15px; font-weight:600;">🔒 Change Password</h3>
            <form method="POST" action="controllers/save_profile_password.php" onsubmit="return validateProfilePassword(this)">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group" style="margin-bottom:12px;">
                    <label>New Password</label>
                    <input type="password" name="password" required minlength="8" placeholder="Minimum 8 characters" style="background:var(--input-bg); color:var(--text-body);">
                </div>
                <div class="form-group" style="margin-bottom:20px;">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required minlength="8" style="background:var(--input-bg); color:var(--text-body);">
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="document.getElementById('profileModal').style.display='none'" style="flex:1; padding:10px; border-radius:8px; border:1px solid var(--border-card); background:transparent; color:var(--text-body); cursor:pointer; font-weight:600;">Cancel</button>
                    <button type="submit" style="flex:1; padding:10px; border-radius:8px; border:none; background:var(--primary-color); color:white; cursor:pointer; font-weight:700;">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openProfileModal() {
        fetch('controllers/get_profile.php')
        .then(res => res.json())
        .then(user => {
            if (user.error) {
                Swal.fire('Error', user.error, 'error');
                return;
            }
            document.getElementById('profileModalName').textContent = user.name || 'N/A';
            document.getElementById('profileModalLoginId').textContent = user.login_id || 'N/A';
            document.getElementById('profileModalRole').textContent = user.role || 'N/A';
            document.getElementById('profileModalEmail').textContent = user.email || 'N/A';
            document.getElementById('profileModalDepartment').textContent = user.department || 'N/A';
            document.getElementById('profileModalBranch').textContent = user.branch_id || 'Global HQ';
            
            document.getElementById('profileModal').style.display = 'flex';
        });
    }

    function validateProfilePassword(form) {
        const pass = form.password.value;
        const conf = form.confirm_password.value;
        if (pass !== conf) {
            Swal.fire('Error', 'Passwords do not match.', 'error');
            return false;
        }
        if (pass.length < 8) {
            Swal.fire('Error', 'Password must be at least 8 characters long.', 'error');
            return false;
        }
        return true;
    }

    // Check for success/error redirect query params
    window.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('profile_success')) {
            Swal.fire({
                title: 'Success!',
                text: 'Your password has been updated successfully.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            });
        }
        if (urlParams.has('profile_error')) {
            Swal.fire({
                title: 'Error',
                text: urlParams.get('profile_error'),
                icon: 'error',
                confirmButtonColor: '#4f46e5'
            }).then(() => {
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            });
        }
    });
    </script>
