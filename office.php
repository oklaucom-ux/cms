<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_office');

// Fetch users for sharing dropdown
$allUsers = $pdo->query("SELECT login_id, name FROM users WHERE status='Active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <!-- FILE EXPLORER VIEW -->
    <div id="fileExplorer">
        <div class="section-header">
            <h2> ☁️ Enterprise Cloud Office </h2>
            <div style="display:flex; gap:10px;">
                <button class="add-button" onclick="createFolder()" style="background:#475569;">📁 New Folder</button>
                <button class="add-button" onclick="openCreator('Word')" style="background:#5a2d82;">📄 New Document</button>
                <button class="add-button" onclick="openCreator('Excel')" style="background:#16a34a;">📊 New Spreadsheet</button>
                <button class="add-button" onclick="openCreator('Powerpoint')" style="background:#ea580c;">📽️ Presentation</button>
            </div>
        </div>
        
        <div id="breadcrumbs" style="margin-top:10px; font-weight:bold; color:#4b5563; font-size:16px;">
            <span style="cursor:pointer; color:#2563eb;" onclick="openFolder(0)">🏠 Root Directory</span>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(250px, 1fr)); gap:20px; margin-top:20px;" id="fileGrid">
            <!-- Files and Folders populated by JS -->
        </div>
    </div>

    <!-- EDITOR VIEW -->
    <div id="editorView" style="display:none; height:calc(100vh - 120px); flex-direction:column;">
        <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:15px 20px; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:20px;">
            <div style="display:flex; align-items:center; gap:15px; flex:1;">
                <button onclick="closeEditor()" style="background:#f3f4f6; color:#4b5563; border:none; padding:8px 16px; border-radius:6px; cursor:pointer; font-weight:bold;">⬅ Back</button>
                <input type="text" id="docName" placeholder="Untitled Document" style="font-size:20px; font-weight:bold; border:none; outline:none; background:transparent; max-width:400px; width:100%;">
                <span id="lockBadge" style="background:#fee2e2; color:#ef4444; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; display:none;">🔒 Locked (Read Only)</span>
                <span id="approvalBadge" style="background:#fef08a; color:#854d0e; padding:4px 8px; border-radius:4px; font-size:12px; font-weight:bold; display:none;">⏳ Pending Approval</span>
            </div>
            
            <div style="display:flex; align-items:center; gap:10px;">
                <select id="docVisibility" style="padding:8px; border-radius:6px; border:1px solid #d1d5db; outline:none;">
                    <option value="Private">🔒 Private</option>
                    <option value="Shared">👥 Shared</option>
                    <option value="Public">🌍 Global</option>
                </select>
                <div id="sharedDropdown" style="display:none; min-width:250px;"></div>
                
                <button id="csvBtn" onclick="exportCSV()" class="view-button" style="background:#059669; color:white; border:none; display:none;">📊 Export CSV</button>
                <button id="pdfBtn" onclick="exportPDF()" class="view-button" style="background:#ef4444; color:white; border:none; display:none;">📄 Export PDF</button>
                
                <button onclick="submitApproval()" id="submitApprovalBtn" class="add-button" style="background:#f59e0b; display:none;">📤 Send for Approval</button>
                <button onclick="processApproval('Approved')" id="approveBtn" class="add-button" style="background:#10b981; display:none;">✅ Approve</button>
                <button onclick="processApproval('Rejected')" id="rejectBtn" class="add-button" style="background:#ef4444; display:none;">❌ Reject</button>

                <button onclick="saveDocument()" id="saveBtn" class="add-button">💾 Save Cloud State</button>
                <span id="saveStatus" style="color:#10b981; font-weight:bold; font-size:13px; display:none;">Saved!</span>
            </div>
        </div>

        <div id="quillWrapper" style="display:none; flex:1; flex-direction:column;">
            <div id="quillCanvas" style="flex:1; background:white; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); overflow:hidden;"></div>
        </div>
        <div id="excelCanvas" style="display:none; flex:1; background:white; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:20px; overflow:auto;"></div>
        <div id="pptCanvas" style="display:none; flex:1; background:white; border-radius:12px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:20px;">
            <div style="display:flex; gap:20px; height:100%;">
                <div style="width:240px; border-right:1px solid #eee; display:flex; flex-direction:column; gap:10px; overflow-y:auto; padding-right:10px;" id="slideList"></div>
                <div style="flex:1; display:flex; flex-direction:column;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                        <select id="pptTheme" onchange="updatePPTTheme()" style="padding:8px; border-radius:6px; border:1px solid #ddd;">
                            <option value="modern">💎 Modern Light</option>
                            <option value="dark">🌙 Midnight Dark</option>
                            <option value="gradient">🌈 Sunset Gradient</option>
                            <option value="corporate">🏢 Corporate Blue</option>
                        </select>
                        <button onclick="presentPPT()" style="padding:8px 20px; background:#ea580c; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">▶ Present Fullscreen</button>
                    </div>
                    <textarea id="slideEditor" style="flex:1; padding:20px; font-size:18px; border:1px solid #ccc; border-radius:8px; resize:none; font-family:monospace;" placeholder="Write Slide Markdown..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dependencies -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script src="https://bossanova.uk/jspreadsheet/v4/jexcel.js"></script>
<link rel="stylesheet" href="https://bossanova.uk/jspreadsheet/v4/jexcel.css" type="text/css" />
<script src="https://jsuites.net/v4/jsuites.js"></script>
<link rel="stylesheet" href="https://jsuites.net/v4/jsuites.css" type="text/css" />

<script>
let currentDocId = null;
let currentDocType = null;
let currentFolderId = 0;
let isReadOnly = false;
let quillEngine = null;
let excelEngine = null;
let pptSlides = [];
let currentSlideIndex = 0;

// Init Shared Dropdown
let sharedDropdown = jSuites.dropdown(document.getElementById('sharedDropdown'), {
    data: <?= json_encode(array_map(fn($u) => ['value' =>$u['login_id'], 'text' =>$u['name']], $allUsers)) ?>,
    multiple: true,
    autocomplete: true,
    width: '250px',
    placeholder: 'Select Users...',
    onchange: () => { clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 3000); }
});

const visSelect = document.getElementById('docVisibility');
visSelect.addEventListener('change', () => {
    document.getElementById('sharedDropdown').style.display = visSelect.value === 'Shared' ? 'inline-block' : 'none';
});

function openFolder(id) {
    currentFolderId = id;
    if(id === 0) {
        document.getElementById('breadcrumbs').innerHTML = '<span style="cursor:pointer; color:#2563eb;" onclick="openFolder(0)">🏠 Root Directory</span>';
    } else {
        document.getElementById('breadcrumbs').innerHTML = '<span style="cursor:pointer; color:#2563eb;" onclick="openFolder(0)">🏠 Root Directory</span> / 📁 Subfolder';
    }
    loadExplorer();
}

function createFolder() {
    let name = prompt("Enter folder name:");
    if (!name) return;
    let formData = new FormData();
    formData.append('action', 'create_folder');
    formData.append('name', name);
    formData.append('parent_id', currentFolderId);
    fetch('controllers/office_api.php', { method: 'POST', body: formData }).then(()=>loadExplorer());
}

function renameFolder(e, id, currentName) {
    e.stopPropagation();
    let name = prompt("Enter new folder name:", currentName);
    if (!name || name === currentName) return;
    let formData = new FormData();
    formData.append('action', 'rename_folder');
    formData.append('id', id);
    formData.append('name', name);
    fetch('controllers/office_api.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res=>{
        if(res.status==='success') loadExplorer(); else alert(res.message);
    });
}

function deleteFolder(e, id) {
    e.stopPropagation();
    if(!confirm("Are you sure you want to delete this folder? Any files inside it will be moved to the Root Directory to prevent data loss.")) return;
    let formData = new FormData();
    formData.append('action', 'delete_folder');
    formData.append('id', id);
    fetch('controllers/office_api.php', { method: 'POST', body: formData }).then(r=>r.json()).then(res=>{
        if(res.status==='success') loadExplorer(); else alert(res.message);
    });
}

function loadExplorer() {
    fetch('controllers/office_api.php?action=list&folder_id=' + currentFolderId)
    .then(r=>r.json())
    .then(res => {
        let h = '';
        
        // Render Folders
        res.data.folders.forEach(f => {
            h += `<div style="background:#f8fafc; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.05); border:1px solid #e2e8f0; cursor:pointer; position:relative;" onclick="openFolder(${f.id})">
                    <div style="position:absolute; top:10px; right:10px; display:flex; gap:5px;">
                        <button style="background:none; border:none; cursor:pointer; font-size:14px;" onclick="renameFolder(event, ${f.id}, '${f.name.replace(/'/g, "\\'")}')" title="Rename Folder">✏️</button>
                        <button style="background:none; border:none; cursor:pointer; font-size:14px;" onclick="deleteFolder(event, ${f.id})" title="Delete Folder">🗑️</button>
                    </div>
                    <div style="font-size:32px; margin-bottom:10px;">📁</div>
                    <h3 style="font-size:16px; margin-bottom:5px; color:#1e293b;">${f.name}</h3>
                  </div>`;
        });
        
        // Render Files
        res.data.files.forEach(f => {
            let icon = f.file_type === 'Word' ? '📄' : (f.file_type === 'Excel' ? '📊' : '📽️');
            let color = f.file_type === 'Word' ? '#5a2d82' : (f.file_type === 'Excel' ? '#16a34a' : '#ea580c');
            let lockHtml = f.locked_by ? `<span style="position:absolute; bottom:10px; right:10px; background:#fee2e2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">🔒 ${f.locked_by}</span>` : '';
            let appBadge = '';
            if (f.approval_status === 'Pending') appBadge = `<span style="background:#fef08a; color:#854d0e; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">⏳ Pending</span>`;
            else if (f.approval_status === 'Approved') appBadge = `<span style="background:#dcfce7; color:#16a34a; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:bold;">✅ Approved</span>`;
            
            h += `<div style="background:white; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,0.05);  cursor:pointer; position:relative;" onclick="openEditor(${f.id})">
                    <div style="font-size:32px; margin-bottom:10px;">${icon}</div>
                    <h3 style="font-size:16px; margin-bottom:5px; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${f.file_name}</h3>
                    <div style="font-size:12px; color:#6b7280; margin-bottom:5px;">By: ${f.created_by} | ${f.visibility}</div>
                    <div>${appBadge}</div>${lockHtml}
                    <button style="position:absolute; top:10px; right:10px; background:none; border:none; cursor:pointer; font-size:16px;" onclick="deleteDoc(event, ${f.id})">🗑️</button>
                  </div>`;
        });
        document.getElementById('fileGrid').innerHTML = h || '<div style="color:#999; text-align:center; grid-column: 1 / -1; padding: 40px;">No items found.</div>';
    });
}
loadExplorer();

function deleteDoc(e, id) {
    e.stopPropagation();
    if(!confirm('Permanently delete this cloud document?')) return;
    let formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
    fetch('controllers/office_api.php', { method: 'POST', body: formData }).then(()=>loadExplorer());
}

function openCreator(type) {
    currentDocId = null;
    currentDocType = type;
    isReadOnly = false;
    document.getElementById('docName').value = 'Untitled ' + type;
    visSelect.value = 'Private';
    sharedDropdown.setValue([]);
    document.getElementById('sharedDropdown').style.display = 'none';
    document.getElementById('lockBadge').style.display = 'none';
    document.getElementById('approvalBadge').style.display = 'none';
    document.getElementById('submitApprovalBtn').style.display = 'none';
    document.getElementById('approveBtn').style.display = 'none';
    document.getElementById('rejectBtn').style.display = 'none';
    document.getElementById('saveBtn').style.display = 'inline-block';
    setupCanvas('', type);
    document.getElementById('fileExplorer').style.display = 'none';
    document.getElementById('editorView').style.display = 'flex';
}

function openEditor(id) {
    fetch(`controllers/office_api.php?action=load&id=${id}`)
    .then(r=>r.json())
    .then(res => {
        if(res.status === 'error') return alert(res.message);
        let f = res.data;
        currentDocId = f.id;
        currentDocType = f.file_type;
        isReadOnly = f.is_readonly;
        
        document.getElementById('docName').value = f.file_name;
        visSelect.value = f.visibility;
        if(f.visibility === 'Shared') {
            sharedDropdown.setValue(JSON.parse(f.shared_with));
            document.getElementById('sharedDropdown').style.display = 'inline-block';
        } else { document.getElementById('sharedDropdown').style.display = 'none'; }
        
        document.getElementById('lockBadge').style.display = isReadOnly ? 'inline-block' : 'none';
        document.getElementById('approvalBadge').style.display = f.approval_status === 'Pending' ? 'inline-block' : 'none';
        
        // Show submit if not pending, show approve/reject if pending and user is not the owner (assuming manager/admin)
        // Wait, for simplicity, just show buttons. The backend will validate. But UI should be clean:
        document.getElementById('submitApprovalBtn').style.display = (f.approval_status !== 'Pending' && f.approval_status !== 'Approved' && f.created_by === '<?= $_SESSION['login_id'] ?>') ? 'inline-block' : 'none';
        
        let canApprove = (f.approval_status === 'Pending' && f.created_by !== '<?= $_SESSION['login_id'] ?>');
        document.getElementById('approveBtn').style.display = canApprove ? 'inline-block' : 'none';
        document.getElementById('rejectBtn').style.display = canApprove ? 'inline-block' : 'none';

        document.getElementById('saveBtn').style.display = isReadOnly ? 'none' : 'inline-block';
        
        setupCanvas(f.json_data, f.file_type);
        document.getElementById('fileExplorer').style.display = 'none';
        document.getElementById('editorView').style.display = 'flex';
    });
}

function closeEditor() {
    if (currentDocId && !isReadOnly) {
        let formData = new FormData();
        formData.append('action', 'unlock');
        formData.append('id', currentDocId);
        fetch('controllers/office_api.php', { method: 'POST', body: formData });
    }
    document.getElementById('fileExplorer').style.display = 'block';
    document.getElementById('editorView').style.display = 'none';
    loadExplorer();
}

function setupCanvas(data, type) {
    document.getElementById('quillWrapper').style.display = 'none';
    document.getElementById('excelCanvas').style.display = 'none';
    document.getElementById('pptCanvas').style.display = 'none';
    document.getElementById('pdfBtn').style.display = type === 'Word' ? 'inline-block' : 'none';
    document.getElementById('csvBtn').style.display = type === 'Excel' ? 'inline-block' : 'none';

    if (type === 'Word') {
        document.getElementById('quillWrapper').style.display = 'flex';
        if (!quillEngine) {
            quillEngine = new Quill('#quillCanvas', {
                theme: 'snow',
                readOnly: isReadOnly,
                modules: { toolbar: [[{header:[1,2,false]}], ['bold','italic','underline'], [{color:[]},{background:[]}], [{list:'ordered'},{list:'bullet'}], [{align:[]}], ['link','image','video'], ['clean']] }
            });
            quillEngine.on('text-change', () => { if(!isReadOnly) { clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 5000); } });
        }
        quillEngine.enable(!isReadOnly);
        quillEngine.root.innerHTML = data || '';
    } 
    else if (type === 'Excel') {
        document.getElementById('excelCanvas').style.display = 'block';
        
        if (document.getElementById('excelCanvas').jexcel) {
            jspreadsheet.destroy(document.getElementById('excelCanvas'));
        }
        document.getElementById('excelCanvas').innerHTML = '';
        
        // Check if data is array of tabs or old 2D array
        let parsed = data ? JSON.parse(data) : [{sheetName:'Sheet1', data:[[]]}];
        if (Array.isArray(parsed) && !parsed[0].sheetName && !parsed[0].options) {
            parsed = [{sheetName: 'Data', data: parsed}]; // Migrate legacy
        }

        excelEngine = jspreadsheet(document.getElementById('excelCanvas'), {
            worksheets: parsed,
            minDimensions: [15, 20], 
            tableOverflow: true, 
            toolbar: true, 
            contextMenu: true,
            editable: !isReadOnly,
            tabs: true,
            onchange: () => { if(!isReadOnly) { clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 5000); } }
        });
    }
    else if (type === 'Powerpoint') {
        document.getElementById('pptCanvas').style.display = 'block';
        let parsed = data ? JSON.parse(data) : { slides: ['# New Presentation\nStart writing...'], theme: 'modern' };
        pptSlides = parsed.slides || [parsed];
        document.getElementById('pptTheme').value = parsed.theme || 'modern';
        currentSlideIndex = 0;
        document.getElementById('slideEditor').value = pptSlides[0];
        document.getElementById('slideEditor').readOnly = isReadOnly;
        renderSlideManager();
    }
}

function renderSlideManager() {
    let h = '';
    pptSlides.forEach((s, i) => {
        let preview = s.split('\n')[0].replace(/[#*]/g, '').substring(0, 20) || 'Empty Slide';
        h += `<div style="padding:12px; margin-bottom:8px; background:${i===currentSlideIndex?'#fed7aa':'#f8fafc'}; border:1px solid #ddd; cursor:pointer; border-radius:8px; font-size:12px;" onclick="switchSlide(${i})">
                <div style="font-weight:bold;">Slide ${i+1}</div><div style="color:#6b7280;">${preview}</div></div>`;
    });
    if(!isReadOnly) h += `<button onclick="addSlide()" style="padding:10px; width:100%; border:2px dashed #ccc; border-radius:8px; cursor:pointer;">+ Add Slide</button>`;
    document.getElementById('slideList').innerHTML = h;
}

function updatePPTTheme() {
    if(!isReadOnly) {
        clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 2000);
    }
}

function switchSlide(idx) {
    if(!isReadOnly) pptSlides[currentSlideIndex] = document.getElementById('slideEditor').value;
    currentSlideIndex = idx;
    document.getElementById('slideEditor').value = pptSlides[idx];
    renderSlideManager();
}

function addSlide() {
    if(isReadOnly) return;
    pptSlides[currentSlideIndex] = document.getElementById('slideEditor').value;
    pptSlides.push('# New Slide');
    switchSlide(pptSlides.length - 1);
}

function exportPDF() {
    const element = document.querySelector('.ql-editor');
    html2pdf().from(element).save(document.getElementById('docName').value + '.pdf');
}

function exportCSV() { 
    if(Array.isArray(excelEngine)) excelEngine[0].download();
    else excelEngine.download(); 
}

function submitApproval() {
    if(!currentDocId) { alert("Save the document first!"); return; }
    if(!confirm("Submit this document to your Manager for approval? It will be locked.")) return;
    
    let fd = new FormData();
    fd.append('action', 'submit_approval');
    fd.append('id', currentDocId);
    fetch('controllers/office_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { closeEditor(); loadExplorer(); } else { alert(res.message); }
    });
}

function processApproval(status) {
    if(!confirm(status + " this document?")) return;
    let fd = new FormData();
    fd.append('action', 'process_approval');
    fd.append('id', currentDocId);
    fd.append('status', status);
    fetch('controllers/office_api.php', { method: 'POST', body: fd }).then(r=>r.json()).then(res=>{
        if(res.status==='success') { closeEditor(); loadExplorer(); } else { alert(res.message); }
    });
}

function saveDocument() {
    if(isReadOnly) return;
    let state = '';
    if (currentDocType === 'Word') state = quillEngine.root.innerHTML;
    else if (currentDocType === 'Excel') {
        let tabs = [];
        if(Array.isArray(excelEngine)) {
            excelEngine.forEach(sheet => tabs.push({sheetName: sheet.config.sheetName, data: sheet.getData()}));
        } else {
            tabs.push({sheetName: 'Sheet1', data: excelEngine.getData()});
        }
        state = JSON.stringify(tabs);
    }
    else if (currentDocType === 'Powerpoint') {
        pptSlides[currentSlideIndex] = document.getElementById('slideEditor').value;
        state = JSON.stringify({ slides: pptSlides, theme: document.getElementById('pptTheme').value });
    }

    let formData = new FormData();
    formData.append('action', 'save');
    if(currentDocId) formData.append('id', currentDocId);
    formData.append('file_type', currentDocType); 
    formData.append('file_name', document.getElementById('docName').value);
    formData.append('visibility', visSelect.value);
    formData.append('shared_with', JSON.stringify(sharedDropdown.getValue()));
    formData.append('folder_id', currentFolderId);
    formData.append('json_data', state);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');

    let btn = document.getElementById('saveBtn');
    btn.innerHTML = 'Saving...';
    fetch('controllers/office_api.php', { method: 'POST', body: formData })
    .then(r=>r.json()).then(res => {
        btn.innerHTML = '💾 Save Cloud State';
        if(res.status === 'success') {
            currentDocId = res.id;
            document.getElementById('saveStatus').style.display = 'inline';
            setTimeout(()=> document.getElementById('saveStatus').style.display = 'none', 2000);
        } else {
            alert(res.message);
        }
    });
}

function presentPPT() {
    if(!isReadOnly) pptSlides[currentSlideIndex] = document.getElementById('slideEditor').value;
    let theme = document.getElementById('pptTheme').value;
    let presentationWindow = window.open("", "_blank");
    let themeStyles = {
        modern: 'background:white; color:#111827; border-top:10px solid #6366f1;',
        dark: 'background:#0f172a; color:white;',
        gradient: 'background:linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color:white;',
        corporate: 'background:#f8fafc; color:#1e293b; border-left:20px solid #1e40af;'
    };

    let html = `<html><head>
        <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"><\/script>
        <style>
            body { ${themeStyles[theme]} display:flex; align-items:center; justify-content:center; height:100vh; font-family:sans-serif; text-align:center; margin:0; padding:60px; box-sizing:border-box; overflow:hidden; transition: background 0.5s; }
            #slideContent { width:100%; max-width:1000px; font-size:32px; animation: fadeIn 0.5s ease-in-out; }
            #slideContent h1 { font-size:72px; margin-bottom:20px; }
            #slideContent p { margin:10px 0; }
            @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        </style></head><body>`;
    html += `<div id="slideContent"></div>`;
    html += `<script>
        let slides = ${JSON.stringify(pptSlides)};
        let curr = 0;
        function render() {
            let content = document.getElementById('slideContent');
            content.style.animation = 'none';
            content.offsetHeight; /* trigger reflow */
            content.style.animation = null; 
            content.innerHTML = marked.parse(slides[curr]);
        }
        document.addEventListener('keydown', (e) => {
            if(e.key === 'ArrowRight' || e.key === ' ') { curr++; if(curr >= slides.length) curr = slides.length-1; render(); }
            if(e.key === 'ArrowLeft') { curr--; if(curr < 0) curr = 0; render(); }
        });
        render();
    <\/script></body></html>`;
    presentationWindow.document.write(html);
}

// Ensure files are unlocked if user closes tab
window.addEventListener("beforeunload", function() {
    if (currentDocId && !isReadOnly) {
        let formData = new FormData();
        formData.append('action', 'unlock');
        formData.append('id', currentDocId);
        navigator.sendBeacon('controllers/office_api.php', formData);
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
