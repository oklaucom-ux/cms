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
                        <div style="display:flex; gap:10px;">
                            <select id="pptTheme" onchange="updatePPTTheme()" style="padding:8px; border-radius:6px; border:1px solid #ddd;">
                                <option value="modern">💎 Modern Light</option>
                                <option value="dark">🌙 Midnight Dark</option>
                                <option value="gradient">🌈 Sunset Gradient</option>
                                <option value="corporate">🏢 Corporate Blue</option>
                                <option value="minimal">🌿 Minimal</option>
                                <option value="retro">📟 Retro 80s</option>
                                <option value="cyberpunk">🌆 Cyberpunk</option>
                                <option value="nature">🍃 Nature</option>
                            </select>
                            <select id="pptTransition" onchange="updatePPTTheme()" style="padding:8px; border-radius:6px; border:1px solid #ddd;">
                                <option value="fade">Fade Transition</option>
                                <option value="slide">Slide Transition</option>
                                <option value="zoom">Zoom Transition</option>
                                <option value="flip">Flip Transition</option>
                            </select>
                        </div>
                        <button onclick="presentPPT()" style="padding:8px 20px; background:#ea580c; color:white; border:none; border-radius:8px; cursor:pointer; font-weight:bold;">▶ Present Fullscreen</button>
                    </div>
                    <div style="display:flex; flex:1; gap:20px; min-height:0;">
                        <!-- Left Pane: Editor -->
                        <div style="flex:1; display:flex; flex-direction:column; border:1px solid #ccc; border-radius:8px; overflow:hidden;">
                            <div id="pptQuillCanvas" style="flex:1; background:#fff;"></div>
                        </div>
                        <!-- Right Pane: Live Preview -->
                        <div style="flex:1; background:#e2e8f0; border-radius:8px; display:flex; align-items:center; justify-content:center; padding:20px; position:relative;">
                            <div style="position:absolute; top:10px; right:10px; font-size:12px; color:#64748b; font-weight:bold;">LIVE PREVIEW</div>
                            <div id="slidePreview" style="width:100%; aspect-ratio: 16/9; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; text-align:center; padding:20px; box-sizing:border-box;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dependencies -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
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
let pptQuillEngine = null;

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
            minDimensions: [26, 50], 
            defaultColWidth: 100,
            tableOverflow: true, 
            tableWidth: "100%",
            tableHeight: "100%",
            toolbar: [
                { type: 'i', content: 'undo', onclick: function(e, i) { i.undo(); } },
                { type: 'i', content: 'redo', onclick: function(e, i) { i.redo(); } },
                { type: 'i', content: 'save', onclick: function() { saveDocument(); } },
                { type: 'select', k: 'font-family', v: ['Arial','Verdana','Courier New','Times New Roman'] },
                { type: 'select', k: 'font-size', v: ['9px','10px','11px','12px','14px','16px','18px','20px'] },
                { type: 'i', content: 'format_align_left', k: 'text-align', v: 'left' },
                { type: 'i', content: 'format_align_center', k: 'text-align', v: 'center' },
                { type: 'i', content: 'format_align_right', k: 'text-align', v: 'right' },
                { type: 'i', content: 'format_bold', k: 'font-weight', v: 'bold' },
                { type: 'i', content: 'format_italic', k: 'font-style', v: 'italic' },
                { type: 'i', content: 'format_underline', k: 'text-decoration', v: 'underline' },
                { type: 'color', content: 'format_color_text', k: 'color' },
                { type: 'color', content: 'format_color_fill', k: 'background-color' },
                { type: 'i', content: 'table_rows', onclick: function(e, i) { i.insertRow(); } },
                { type: 'i', content: 'view_column', onclick: function(e, i) { i.insertColumn(); } },
                { type: 'i', content: 'wrap_text', onclick: function(e, i) { 
                    let sel = i.getSelected(); 
                    if(sel) { i.setStyle(sel, 'white-space', 'normal'); } 
                } },
                { type: 'i', content: 'link', onclick: function(e, i) { 
                    let url = prompt('Enter URL:'); 
                    if(url) { 
                        let cell = i.getSelected(); 
                        if(cell && cell.length > 0) {
                            i.setValue(cell, '=HYPERLINK("' + url + '", "Link")');
                        }
                    } 
                } },
                { type: 'i', content: 'fullscreen', onclick: function(e, i) { i.fullscreen(true); } }
            ],
            editable: !isReadOnly,
            tabs: true,
            onchange: () => { if(!isReadOnly) { clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 5000); } }
        });
    }
    else if (type === 'Powerpoint') {
        document.getElementById('pptCanvas').style.display = 'block';
        let parsed = data ? JSON.parse(data) : { slides: ['<h1>New Presentation</h1><p>Start writing...</p>'], theme: 'modern', transition: 'fade' };
        pptSlides = parsed.slides || [parsed];
        document.getElementById('pptTheme').value = parsed.theme || 'modern';
        document.getElementById('pptTransition').value = parsed.transition || 'fade';
        currentSlideIndex = 0;
        
        if (!pptQuillEngine) {
            pptQuillEngine = new Quill('#pptQuillCanvas', {
                theme: 'snow',
                readOnly: isReadOnly,
                modules: { toolbar: [[{header:[1,2,3,false]}], ['bold','italic','underline','strike'], [{color:[]},{background:[]}], [{list:'ordered'},{list:'bullet'}], [{align:[]}], ['link','image'], ['clean']] }
            });
            pptQuillEngine.on('text-change', () => { 
                if(!isReadOnly) { 
                    pptSlides[currentSlideIndex] = pptQuillEngine.root.innerHTML;
                    renderLivePreview();
                    clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 2000); 
                } 
            });
        }
        
        pptQuillEngine.enable(!isReadOnly);
        pptQuillEngine.root.innerHTML = pptSlides[0];
        renderSlideManager();
        renderLivePreview();
    }
}

function getThemeStyles(theme) {
    let themes = {
        modern: 'background:white; color:#111827; border-top:10px solid #6366f1;',
        dark: 'background:#0f172a; color:white;',
        gradient: 'background:linear-gradient(135deg, #6366f1 0%, #a855f7 100%); color:white;',
        corporate: 'background:#f8fafc; color:#1e293b; border-left:20px solid #1e40af;',
        minimal: 'background:#ffffff; color:#333; font-family:serif;',
        retro: 'background:#000; color:#0f0; font-family:monospace; border:5px solid #0f0;',
        cyberpunk: 'background:#120458; color:#ff003c; text-shadow: 2px 2px 0px #00e6fe; border-bottom:10px solid #fce803;',
        nature: 'background:#f0fdf4; color:#14532d; border-top:15px solid #22c55e;'
    };
    return themes[theme] || themes['modern'];
}

function renderLivePreview() {
    let preview = document.getElementById('slidePreview');
    preview.style.cssText = getThemeStyles(document.getElementById('pptTheme').value) + ' width:100%; aspect-ratio: 16/9; box-shadow:0 10px 25px rgba(0,0,0,0.1); overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center; text-align:center; padding:20px; box-sizing:border-box; font-family:sans-serif; transition:all 0.3s;';
    preview.innerHTML = `<div style="transform: scale(0.6); width:160%; height:160%; display:flex; flex-direction:column; align-items:center; justify-content:center;">${pptSlides[currentSlideIndex]}</div>`;
}

function renderSlideManager() {
    let h = '';
    pptSlides.forEach((s, i) => {
        let previewText = s.replace(/<[^>]*>?/gm, '').substring(0, 20) || 'Empty Slide';
        h += `<div style="padding:12px; margin-bottom:8px; background:${i===currentSlideIndex?'#fed7aa':'#f8fafc'}; border:1px solid #ddd; cursor:pointer; border-radius:8px; font-size:12px; position:relative;" onclick="switchSlide(${i})">
                <div style="font-weight:bold;">Slide ${i+1}</div>
                <div style="color:#6b7280; overflow:hidden; white-space:nowrap; text-overflow:ellipsis;">${previewText}</div>
                ${!isReadOnly ? `
                <div style="position:absolute; right:5px; top:5px; display:flex; flex-direction:column; gap:2px;">
                    <button onclick="moveSlide(event, ${i}, -1)" style="font-size:10px; padding:2px; border:none; cursor:pointer; background:none;">⬆️</button>
                    <button onclick="moveSlide(event, ${i}, 1)" style="font-size:10px; padding:2px; border:none; cursor:pointer; background:none;">⬇️</button>
                </div>
                ` : ''}
              </div>`;
    });
    if(!isReadOnly) {
        h += `<div style="display:flex; gap:10px;">
                <button onclick="addSlide()" style="padding:10px; flex:1; border:2px dashed #ccc; border-radius:8px; cursor:pointer;">+ Add</button>
                <button onclick="duplicateSlide()" style="padding:10px; flex:1; border:2px solid #ccc; border-radius:8px; cursor:pointer;">📋 Dup</button>
              </div>`;
    }
    document.getElementById('slideList').innerHTML = h;
}

function moveSlide(e, idx, dir) {
    e.stopPropagation();
    if (idx + dir < 0 || idx + dir >= pptSlides.length) return;
    let temp = pptSlides[idx];
    pptSlides[idx] = pptSlides[idx + dir];
    pptSlides[idx + dir] = temp;
    switchSlide(idx + dir);
}

function duplicateSlide() {
    if(isReadOnly) return;
    pptSlides.splice(currentSlideIndex + 1, 0, pptSlides[currentSlideIndex]);
    switchSlide(currentSlideIndex + 1);
}

function updatePPTTheme() {
    if(!isReadOnly) {
        renderLivePreview();
        clearTimeout(window.saveTimer); window.saveTimer = setTimeout(saveDocument, 2000);
    }
}

function switchSlide(idx) {
    if(!isReadOnly && pptQuillEngine) pptSlides[currentSlideIndex] = pptQuillEngine.root.innerHTML;
    currentSlideIndex = idx;
    pptQuillEngine.root.innerHTML = pptSlides[idx];
    renderSlideManager();
    renderLivePreview();
}

function addSlide() {
    if(isReadOnly) return;
    if(pptQuillEngine) pptSlides[currentSlideIndex] = pptQuillEngine.root.innerHTML;
    pptSlides.push('<h1>New Slide</h1><p>Content...</p>');
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
        if(pptQuillEngine) pptSlides[currentSlideIndex] = pptQuillEngine.root.innerHTML;
        state = JSON.stringify({ slides: pptSlides, theme: document.getElementById('pptTheme').value, transition: document.getElementById('pptTransition').value });
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
    if(!isReadOnly && pptQuillEngine) pptSlides[currentSlideIndex] = pptQuillEngine.root.innerHTML;
    let theme = document.getElementById('pptTheme').value;
    let transition = document.getElementById('pptTransition').value;
    let presentationWindow = window.open("", "_blank");
    let themeCss = getThemeStyles(theme);

    let html = `<html><head>
        <style>
            body { display:flex; align-items:center; justify-content:center; height:100vh; margin:0; padding:0; box-sizing:border-box; overflow:hidden; background:black; font-family:sans-serif; }
            #slideContainer { width:100%; height:100%; display:flex; align-items:center; justify-content:center; text-align:center; padding:60px; box-sizing:border-box; ${themeCss} position:relative; overflow:hidden; }
            #slideContent { width:100%; max-width:1200px; font-size:36px; transition: all 0.5s; }
            #slideContent h1 { font-size:80px; margin-bottom:20px; }
            #slideContent p { margin:15px 0; }
            #slideContent img { max-width:100%; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.2); }
            
            /* Transitions */
            .trans-fade-enter { opacity:0; }
            .trans-fade-enter-active { opacity:1; transition: opacity 0.5s; }
            .trans-slide-enter { transform: translateX(100vw); }
            .trans-slide-enter-active { transform: translateX(0); transition: transform 0.5s; }
            .trans-zoom-enter { transform: scale(0.5); opacity: 0; }
            .trans-zoom-enter-active { transform: scale(1); opacity: 1; transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
            .trans-flip-enter { transform: perspective(800px) rotateY(-90deg); opacity: 0; }
            .trans-flip-enter-active { transform: perspective(800px) rotateY(0deg); opacity: 1; transition: all 0.6s ease-out; }

            #progress { position:absolute; bottom:0; left:0; height:6px; background:rgba(255,255,255,0.7); transition: width 0.3s; box-shadow:0 -1px 5px rgba(0,0,0,0.2); }
            #counter { position:absolute; bottom:20px; right:20px; font-size:18px; color:inherit; opacity:0.6; font-weight:bold; }
            
            /* Add Quill Core CSS logic to render properly */
            .ql-align-center { text-align: center; }
            .ql-align-right { text-align: right; }
            .ql-align-justify { text-align: justify; }
        </style></head><body>`;
    html += `<div id="slideContainer" onclick="nextSlide()"><div id="slideContent"></div><div id="progress"></div><div id="counter"></div></div>`;
    html += `<script>
        let slides = ${JSON.stringify(pptSlides)};
        let curr = 0;
        let transition = "${transition}";
        function render() {
            let content = document.getElementById('slideContent');
            content.className = 'trans-' + transition + '-enter';
            content.innerHTML = slides[curr];
            
            // Progress
            document.getElementById('progress').style.width = ((curr+1) / slides.length * 100) + '%';
            document.getElementById('counter').innerText = 'Slide ' + (curr+1) + ' / ' + slides.length;
            
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    content.className = 'trans-' + transition + '-enter-active';
                });
            });
        }
        function nextSlide() { if(curr < slides.length-1) { curr++; render(); } }
        function prevSlide() { if(curr > 0) { curr--; render(); } }
        
        document.addEventListener('keydown', (e) => {
            if(e.key === 'ArrowRight' || e.key === ' ' || e.key === 'Enter') { nextSlide(); }
            if(e.key === 'ArrowLeft' || e.key === 'Backspace') { prevSlide(); }
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
