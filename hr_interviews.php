<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'manage_recruitment');
if(!hasPermission($pdo, 'manage_users') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    die("<div class='content-section active'><h2>Access Denied</h2><p>HR or Admin privileges required.</p></div>");
}

try {
    $apiKey = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key='openai_api_key'")->fetchColumn();
} catch (Exception $e) { $apiKey = ''; }
?>

<div class="content-section active">
    <div class="section-header">
        <div style="display:flex; align-items:center; gap:16px;">
            <div style="width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg, #ec4899, #be185d); display:flex; align-items:center; justify-content:center; box-shadow:0 10px 20px rgba(236,72,153,0.3);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
            </div>
            <div>
                <h2 style="margin-bottom:4px; font-size:24px; font-weight:800; color:var(--text-heading);">Virtual HR: Automated Interviews</h2>
                <p style="color:var(--text-muted); font-size:15px;">AI-driven technical assessments and video interviews.</p>
            </div>
        </div>
        <div style="display:flex; gap:12px;">
            <button class="add-button" onclick="document.getElementById('templateModal').style.display='flex'" style="background:var(--bg-card); color:var(--text-heading); border:1px solid var(--border-card); box-shadow:0 2px 8px rgba(0,0,0,0.05); border-radius:10px; font-weight:700; padding:10px 20px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; margin-bottom:-4px;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                Create Template
            </button>
            <button class="add-button" onclick="openSessionModal()" style="background:linear-gradient(135deg, #ec4899, #be185d); box-shadow:0 4px 12px rgba(236,72,153,0.3); border-radius:10px; font-weight:700; padding:10px 20px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px; margin-bottom:-4px;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                Generate Link
            </button>
        </div>
    </div>

    <!-- TABS -->
    <div style="display:flex; gap:10px; margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:10px;">
        <button id="tab-sessions" onclick="switchTab('sessions')" style="background:none; border:none; padding:10px 20px; font-weight:bold; font-size:16px; cursor:pointer; color:#4f46e5; border-bottom:3px solid #4f46e5;">Interview Sessions</button>
        <button id="tab-templates" onclick="switchTab('templates')" style="background:none; border:none; padding:10px 20px; font-weight:bold; font-size:16px; cursor:pointer; color:#64748b;">Question Templates</button>
        <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
        <button id="tab-settings" onclick="switchTab('settings')" style="background:none; border:none; padding:10px 20px; font-weight:bold; font-size:16px; cursor:pointer; color:#64748b; margin-left:auto;">⚙️ AI Settings</button>
        <?php endif; ?>
    </div>

    <!-- SESSIONS VIEW -->
    <div id="view-sessions">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(350px, 1fr)); gap:20px;" id="sessionsGrid"></div>
    </div>

    <!-- TEMPLATES VIEW -->
    <div id="view-templates" style="display:none;">
        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:20px;" id="templatesGrid"></div>
    </div>
    
    <!-- SETTINGS VIEW -->
    <div id="view-settings" style="display:none; max-width:600px;">
        <div style="background:white; padding:30px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
            <h3 style="margin-top:0;">OpenAI API Configuration</h3>
            <p style="color:#64748b; font-size:14px; margin-bottom:20px;">Provide your OpenAI API Key to enable automated Question Generation and Transcript Analysis.</p>
            <form onsubmit="saveOpenAIKey(event)">
                <input type="password" id="openai_key" value="<?= htmlspecialchars($apiKey) ?>" placeholder="sk-proj-..." style="width:100%; padding:15px; border:1px solid #cbd5e1; border-radius:6px; font-family:monospace;" required>
                <button type="submit" class="add-button" style="background:#2563eb; margin-top:15px;">Save Key</button>
            </form>
        </div>
    </div>
</div>

<!-- Template Modal -->
<div class="modal" id="templateModal">
    <div class="modal-content" style="width:700px; max-height:90vh; overflow-y:auto;">
        <h2>Create Interview Template</h2>
        
        <div style="background: linear-gradient(to right, #fef3c7, #fffbeb);  padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <h4 style="margin:0 0 10px 0; color:#d97706;">🤖 AI Question Generator</h4>
            <div style="display:flex; gap:10px; margin-bottom:10px;">
                <input type="text" id="ai_kra" placeholder="Key Responsibility Area (e.g., Frontend Dev)" style="flex:1;">
                <select id="ai_skill" style="flex:1;">
                    <option value="Junior">Junior Level</option>
                    <option value="Mid-Level">Mid-Level</option>
                    <option value="Senior">Senior Level</option>
                </select>
            </div>
            <textarea id="ai_cv" placeholder="Paste Candidate CV Excerpt here (Optional)..." rows="3" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; outline:none; font-family:sans-serif;"></textarea>
            <button type="button" onclick="generateAIQuestions()" id="aiGenBtn" style="background:#f59e0b; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:bold; margin-top:10px;">✨ Generate Questions</button>
        </div>

        <form id="templateForm" onsubmit="saveTemplate(event)">
            <label>Template Title</label>
            <input type="text" id="tpl_title" required>
            <label>Expected Keywords (Comma separated, for standard scoring)</label>
            <input type="text" id="tpl_keywords" placeholder="oop, api, scalability, teamwork">
            
            <div style="margin-top:20px; border-top:1px solid #e2e8f0; padding-top:10px;">
                <label>Questions</label>
                <div id="qList"></div>
                <button type="button" onclick="addQuestionField()" style="background:#f1f5f9; border:1px dashed #cbd5e1; padding:8px; width:100%; margin-top:10px; border-radius:6px; cursor:pointer;">+ Add Manual Question</button>
            </div>
            
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('templateModal').style.display='none'" style="background:#ccc; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" class="add-button" style="background:#4f46e5;">Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- Generate Link Modal -->
<div class="modal" id="sessionModal">
    <div class="modal-content">
        <h2>Generate Interview Link</h2>
        <form id="sessionForm" onsubmit="generateSession(event)">
            <label>Select Template</label>
            <select id="ses_template" required></select>
            
            <label>Candidate Name</label>
            <input type="text" id="ses_name" required>
            
            <label>Candidate Email (Optional - Will send trigger mail)</label>
            <input type="email" id="ses_email" placeholder="candidate@example.com">
            
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('sessionModal').style.display='none'" style="background:#ccc; border:none; padding:10px 20px; border-radius:6px; cursor:pointer;">Cancel</button>
                <button type="submit" class="add-button" style="background:#10b981;">Generate Code</button>
            </div>
        </form>
    </div>
</div>

<!-- Review Transcript Modal -->
<div class="modal" id="reviewModal">
    <div class="modal-content" style="width:800px; max-height:90vh; overflow-y:auto; background:#f8fafc;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 id="revTitle" style="margin:0;">Review Transcript</h2>
            <button onclick="document.getElementById('reviewModal').style.display='none'" style="background:#ccc; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;">Close</button>
        </div>
        
        <div style="display:flex; gap:20px; margin-bottom:20px;">
            <div style="flex:1; background:white; padding:20px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border:1px solid #e2e8f0;">
                <h4 style="margin:0 0 10px 0; color:#475569;">🪪 Candidate Identity</h4>
                <div id="revIdPhoto" style="width:100%; height:150px; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#94a3b8; overflow:hidden;">
                    No Photo Found
                </div>
            </div>
            <div style="flex:1; background:white; padding:20px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border:1px solid #e2e8f0;">
                <h4 style="margin:0 0 10px 0; color:#475569;">🛡️ Anti-Cheat Tracking</h4>
                <div id="revCheatFlags" style="font-size:36px; font-weight:bold; color:#ef4444; text-align:center; margin-top:20px;">0</div>
                <div style="text-align:center; font-size:12px; color:#64748b;">Tab Switch Violations</div>
            </div>
            <div style="flex:1; background:white; padding:20px; border-radius:12px; box-shadow:0 2px 5px rgba(0,0,0,0.05); border:1px solid #e2e8f0;">
                <h4 style="margin:0 0 10px 0; color:#475569;">🎯 Keyword Score</h4>
                <div id="revScore" style="font-size:36px; font-weight:bold; color:#10b981; text-align:center; margin-top:20px;">0%</div>
                <div style="text-align:center; font-size:12px; color:#64748b;">Technical Match</div>
            </div>
        </div>
        
        <div id="aiAnalysisBox" style="background: linear-gradient(to right, #e0e7ff, #ede9fe); padding:20px; border-radius:12px; margin-bottom:20px; display:none; border:1px solid #c7d2fe;">
            <h4 style="margin:0 0 10px 0; color:#4338ca;">🧠 OpenAI Advanced Analysis</h4>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                <div><strong style="color:#3730a3;">Sentiment/Confidence:</strong><br><span id="aiSentiment" style="font-size:14px; color:#4f46e5;"></span></div>
                <div><strong style="color:#3730a3;">Communication:</strong><br><span id="aiComm" style="font-size:14px; color:#4f46e5;"></span></div>
                <div style="grid-column:1 / -1;"><strong style="color:#3730a3;">Overall Feedback:</strong><br><span id="aiFeedback" style="font-size:14px; color:#4f46e5;"></span></div>
            </div>
        </div>
        
        <h3 style="color:#0f172a; margin-bottom:15px;">Detailed Transcript & Video</h3>
        <div id="revContent"></div>
    </div>
</div>

<style>
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; }
.modal-content { background:white; padding:30px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
.modal-content label { display:block; margin-top:15px; font-weight:bold; font-size:14px; color:#475569; }
.modal-content input, .modal-content select { width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px; outline:none; }
</style>

<script>
let globalTemplates = [];
let globalSessions = [];

function switchTab(tab) {
    document.getElementById('view-sessions').style.display = tab === 'sessions' ? 'block' : 'none';
    document.getElementById('view-templates').style.display = tab === 'templates' ? 'block' : 'none';
    document.getElementById('view-settings').style.display = tab === 'settings' ? 'block' : 'none';
    
    document.getElementById('tab-sessions').style.color = tab === 'sessions' ? '#4f46e5' : '#64748b';
    document.getElementById('tab-sessions').style.borderBottom = tab === 'sessions' ? '3px solid #4f46e5' : 'none';
    
    document.getElementById('tab-templates').style.color = tab === 'templates' ? '#10b981' : '#64748b';
    document.getElementById('tab-templates').style.borderBottom = tab === 'templates' ? '3px solid #10b981' : 'none';
    
    if(document.getElementById('tab-settings')) {
        document.getElementById('tab-settings').style.color = tab === 'settings' ? '#2563eb' : '#64748b';
        document.getElementById('tab-settings').style.borderBottom = tab === 'settings' ? '3px solid #2563eb' : 'none';
    }
}

function saveOpenAIKey(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'save_openai_key');
    fd.append('api_key', document.getElementById('openai_key').value);
    fetch('controllers/interview_api.php', {method:'POST', body:fd}).then(()=>alert('API Key Saved!'));
}

function loadDashboard() {
    fetch('controllers/interview_api.php?action=list_templates')
    .then(r=>r.json()).then(res => {
        globalTemplates = res.data;
        let h = '';
        res.data.forEach(t => {
            h += `<div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div style="font-size:24px; margin-bottom:10px;">📋</div>
                    <h3 style="font-size:18px; margin-bottom:5px; color:#0f172a;">${t.title}</h3>
                    <div style="font-size:12px; color:#64748b; margin-bottom:15px;">Keywords: ${t.expected_keywords || 'None'}</div>
                  </div>`;
        });
        document.getElementById('templatesGrid').innerHTML = h || '<p>No templates.</p>';
    });

    fetch('controllers/interview_api.php?action=list_sessions')
    .then(r=>r.json()).then(res => {
        globalSessions = res.data;
        let h = '';
        res.data.forEach(s => {
            let statusColor = '#64748b';
            if(s.status === 'Completed') statusColor = '#10b981';
            if(s.status === 'Phase 2 Scheduled') statusColor = '#8b5cf6';
            if(s.status === 'In Progress') statusColor = '#f59e0b';
            
            let reviewBtn = (s.status === 'Completed' || s.status === 'Phase 2 Scheduled') ? `<button onclick="reviewSession(${s.id})" style="background:#4f46e5; color:white; border:none; padding:6px 12px; border-radius:6px; cursor:pointer; font-size:12px; margin-top:10px;">👀 Review File</button>` : '';
            let flagIcon = s.anti_cheat_flags > 0 ? `<span style="color:#ef4444; font-size:16px; margin-left:10px;" title="${s.anti_cheat_flags} Anti-Cheat Violations">🚩</span>` : '';
            
            h += `<div style="background:white; padding:20px; border-radius:12px; border:1px solid #e2e8f0;  box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <h3 style="font-size:18px; margin-bottom:5px; color:#0f172a; display:flex; align-items:center;">👤 ${s.candidate_name} ${flagIcon}</h3>
                    <div style="font-size:13px; color:#475569; font-weight:bold; margin-bottom:10px;">Role: ${s.template_title}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; background:#f8fafc; padding:10px; border-radius:6px; margin-bottom:10px;">
                        <span style="font-size:12px; color:#64748b;">Code:</span>
                        <strong style="font-family:monospace; font-size:16px; color:#2563eb; letter-spacing:2px;">${s.access_code}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12px; font-weight:bold; color:${statusColor};">${s.status}</span>${(s.status==='Completed' || s.status==='Phase 2 Scheduled') ? `<span style="font-size:14px; font-weight:bold; color:#10b981;">Score: ${s.total_score}%</span>` : ''}
                    </div>${reviewBtn}
                  </div>`;
        });
        document.getElementById('sessionsGrid').innerHTML = h || '<p>No sessions generated.</p>';
    });
}
loadDashboard();

function generateAIQuestions() {
    let btn = document.getElementById('aiGenBtn');
    btn.innerText = "⏳ Generating with OpenAI...";
    btn.disabled = true;
    
    let fd = new FormData();
    fd.append('action', 'ai_generate_questions');
    fd.append('kra', document.getElementById('ai_kra').value);
    fd.append('skill_level', document.getElementById('ai_skill').value);
    fd.append('cv_text', document.getElementById('ai_cv').value);
    
    fetch('controllers/interview_api.php', {method:'POST', body:fd})
    .then(r=>r.json()).then(res => {
        btn.innerText = "✨ Generate Questions";
        btn.disabled = false;
        
        if(res.status === 'success') {
            document.getElementById('qList').innerHTML = ''; // Clear manual
            res.questions.forEach(q => {
                let div = document.createElement('div');
                div.style.display = 'flex'; div.style.gap = '10px'; div.style.marginTop = '10px';
                div.innerHTML = `
                    <input type="text" class="q_text" value="${q.text}" required style="flex:1;">
                    <input type="number" class="q_time" value="${q.time}" required style="width:100px;">
                    <button type="button" onclick="this.parentElement.remove()" style="background:#fee2e2; color:#ef4444; border:none; border-radius:6px; cursor:pointer; padding:0 10px;">X</button>
                `;
                document.getElementById('qList').appendChild(div);
            });
            alert("AI Questions Generated Successfully!");
        } else {
            alert("AI Generation Failed: " + res.message);
        }
    });
}

function addQuestionField() {
    let div = document.createElement('div');
    div.style.display = 'flex'; div.style.gap = '10px'; div.style.marginTop = '10px';
    div.innerHTML = `
        <input type="text" class="q_text" placeholder="Question Text" required style="flex:1;">
        <input type="number" class="q_time" placeholder="Seconds" value="120" required style="width:100px;">
        <button type="button" onclick="this.parentElement.remove()" style="background:#fee2e2; color:#ef4444; border:none; border-radius:6px; cursor:pointer; padding:0 10px;">X</button>
    `;
    document.getElementById('qList').appendChild(div);
}
addQuestionField();

function saveTemplate(e) {
    e.preventDefault();
    let qTexts = document.querySelectorAll('.q_text');
    let qTimes = document.querySelectorAll('.q_time');
    let questions = [];
    for(let i=0; i<qTexts.length; i++) {
        questions.push({ text: qTexts[i].value, time: qTimes[i].value });
    }
    
    let fd = new FormData();
    fd.append('action', 'create_template');
    fd.append('title', document.getElementById('tpl_title').value);
    fd.append('expected_keywords', document.getElementById('tpl_keywords').value);
    fd.append('questions', JSON.stringify(questions));
    
    fetch('controllers/interview_api.php', {method:'POST', body:fd}).then(()=> {
        document.getElementById('templateModal').style.display='none';
        document.getElementById('qList').innerHTML='';
        addQuestionField();
        loadDashboard();
        switchTab('templates');
    });
}

function openSessionModal() {
    let opts = '<option value="">Select...</option>';
    globalTemplates.forEach(t => opts += `<option value="${t.id}">${t.title}</option>`);
    document.getElementById('ses_template').innerHTML = opts;
    document.getElementById('sessionForm').reset();
    document.getElementById('sessionModal').style.display = 'flex';
}

function generateSession(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'generate_session');
    fd.append('template_id', document.getElementById('ses_template').value);
    fd.append('candidate_name', document.getElementById('ses_name').value);
    fd.append('candidate_email', document.getElementById('ses_email').value);
    
    fetch('controllers/interview_api.php', {method:'POST', body:fd})
    .then(r=>r.json()).then(res => {
        document.getElementById('sessionModal').style.display='none';
        alert("Interview Code Generated: " + res.access_code + "\n\nAn email has been triggered to the candidate.");
        loadDashboard();
        switchTab('sessions');
    });
}

function reviewSession(id) {
    let s = globalSessions.find(x => x.id === id);
    if(!s) return;
    document.getElementById('revTitle').innerText = "Transcript: " + s.candidate_name;
    
    document.getElementById('revScore').innerText = s.total_score + "%";
    document.getElementById('revCheatFlags').innerText = s.anti_cheat_flags;
    document.getElementById('revCheatFlags').style.color = s.anti_cheat_flags > 0 ? '#ef4444' : '#10b981';
    
    if (s.id_photo_path) {
        document.getElementById('revIdPhoto').innerHTML = `<img src="${s.id_photo_path}" style="max-width:100%; max-height:100%; border-radius:8px; object-fit:cover;">`;
    } else {
        document.getElementById('revIdPhoto').innerHTML = "No Photo Found";
    }
    
    let aiBox = document.getElementById('aiAnalysisBox');
    if (s.ai_analysis) {
        try {
            let ai = JSON.parse(s.ai_analysis);
            document.getElementById('aiSentiment').innerText = ai.sentiment || 'N/A';
            document.getElementById('aiComm').innerText = ai.communication || 'N/A';
            document.getElementById('aiFeedback').innerText = ai.feedback || 'N/A';
            aiBox.style.display = 'block';
        } catch(e) { aiBox.style.display = 'none'; }
    } else {
        aiBox.style.display = 'none';
    }
    
    let h = '';
    s.answers.forEach((a, i) => {
        let scColor = a.score > 50 ? '#10b981' : '#ef4444';
        let videoHtml = a.video_path ? `<div style="margin-top:15px;"><video src="${a.video_path}" controls style="width:100%; border-radius:8px; background:#000;"></video></div>` : '';
        
        h += `<div style="margin-bottom:20px; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; background:white;">
                <div style="background:#f1f5f9; padding:10px 15px; font-weight:bold; color:#334155; border-bottom:1px solid #e2e8f0;">Q${i+1}: ${a.question_text}</div>
                <div style="padding:15px; font-size:15px; line-height:1.5; color:#0f172a; white-space:pre-wrap;">${a.candidate_answer || '<span style="color:#94a3b8;font-style:italic;">No answer recorded.</span>'}
                    ${videoHtml}
                </div>
                <div style="background:#f8fafc; padding:8px 15px; font-size:12px; color:#64748b; display:flex; justify-content:space-between;">
                    <span>Time Taken: ${a.time_taken}s</span>
                    <span style="color:${scColor}; font-weight:bold;">Keyword Match: ${a.score}%</span>
                </div>
              </div>`;
    });
    
    document.getElementById('revContent').innerHTML = h;
    document.getElementById('reviewModal').style.display = 'flex';
}
</script>

<?php require_once 'includes/footer.php'; ?>
