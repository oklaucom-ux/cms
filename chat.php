<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';



// Prepare dynamic table creation
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    sender_id TEXT NOT NULL,
    receiver_id TEXT NOT NULL,
    message TEXT NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(255) DEFAULT 'unread'
)");

$au_stmt = $pdo->prepare("SELECT login_id, name FROM users WHERE login_id != ? AND status = 'Active' ORDER BY name ASC");
$au_stmt->execute([$_SESSION['login_id']]);
$all_users = $au_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch channels
try { $pdo->exec("CREATE TABLE IF NOT EXISTS chat_channels (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255) NOT NULL UNIQUE, description TEXT)"); } catch(Exception $e){}
$channels = $pdo->query("SELECT * FROM chat_channels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
if(empty($channels)) {
    $pdo->exec("INSERT INTO chat_channels (name, description) VALUES ('#General', 'Company-wide Broadcast'), ('#Sales', 'Lead & Client Discussion'), ('#Engineering', 'Tech & Development')");
    $channels = $pdo->query("SELECT * FROM chat_channels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<style>
.chat-container { display:flex; height:calc(100vh - 120px); background:var(--bg-card); border-radius:var(--radius-lg); border:1px solid var(--border-card); overflow:hidden; box-shadow:var(--shadow-sm); }
.user-list { width:280px; border-right:1px solid var(--border-card); background:var(--bg-card); overflow-y:auto; flex-shrink:0; }
.user-list-item { padding:12px 16px; border-bottom:1px solid var(--border-card); cursor:pointer; transition:background .12s; }
.user-list-item:hover { background:var(--bg-hover); }
.user-list-item.selected { background:var(--primary-color); }
.user-list-item.selected strong { color:#ffffff; }
.user-list-item.selected span { color:rgba(255,255,255,0.8); }
.user-list-item strong { display:block; color:var(--primary-color); font-size:13px; font-weight:600; }
.user-list-item span { font-size:12px; color:var(--text-muted); }
.chat-box { flex:1; display:flex; flex-direction:column; background:var(--bg-main); }
.chat-header { padding:14px 18px; background:var(--primary-color); color:#fff; font-weight:600; font-size:14px; }
.chat-messages { flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:8px; }
.msg-bubble { max-width:70%; padding:10px 14px; border-radius:16px; font-size:13px; line-height:1.5; word-break:break-word; }
.msg-bubble.sent { background:var(--primary-color); color:#fff; align-self:flex-end; border-bottom-right-radius:4px; }
.msg-bubble.received { background:var(--bg-card); color:var(--text-body); align-self:flex-start; border-bottom-left-radius:4px; border:1px solid var(--border-card); }
.msg-time { font-size:10px; margin-top:4px; opacity:.7; text-align:right; }
.chat-input { padding:12px 16px; border-top:1px solid var(--border-card); display:flex; gap:10px; background:var(--bg-card); align-items:center; }
.chat-input input { flex:1; padding:10px 16px; border:1px solid var(--input-border); border-radius:99px; outline:none; background:var(--input-bg); color:var(--text-body); font-size:13px; font-family:inherit; }
.chat-input input:focus { border-color:var(--primary-color); box-shadow:0 0 0 3px rgba(79,70,229,.1); }
.chat-input button { height:38px; padding:0 20px; background:var(--primary-color); color:#fff; border:none; border-radius:99px; cursor:pointer; font-weight:600; font-size:13px; transition:background .15s; white-space:nowrap; }
.chat-input button:hover { background:var(--primary-hover); }
</style>

<div class="content-section active" style="padding-top:0;">
    <div class="section-header" style="margin: 20px 0;">
        <h2>Corporate Communication Hub</h2>
    </div>
    
    <div class="chat-container">
        <!-- User Directory Sidebar -->
        <div class="user-list">
            <div style="padding: 10px 15px; font-size: 11px; font-weight: bold; color: #9ca3af; text-transform: uppercase; background: #f3f4f6; position:sticky; top:0; display:flex; justify-content:space-between; align-items:center;">
                Public Channels
                <?php if(hasPermission($pdo, 'moderate_chat')): ?>
                <span onclick="document.getElementById('channelModal').style.display='flex'" style="cursor:pointer; font-size:14px;" title="Create Channel">➕</span>
                <?php endif; ?>
            </div>
            
            <div id="dynamicChannelsList">
                <?php foreach($channels as $c):
                    $cId   = json_encode($c['name']);
                    $cName = json_encode($c['name']);
                    $cDesc = htmlspecialchars($c['description'] ?? 'Discussion channel');
                ?>
                <div class="user-list-item channel-item" style="display:flex; justify-content:space-between; align-items:center;">
                    <div onclick="selectUser(event, <?= htmlspecialchars($cId, ENT_QUOTES) ?>, <?= htmlspecialchars($cName, ENT_QUOTES) ?>)" style="flex:1;">
                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                        <span><?= $cDesc ?></span>
                    </div>
                    <?php if(hasPermission($pdo, 'moderate_chat')): ?>
                    <span onclick="openEditChannelModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($c['description']), ENT_QUOTES) ?>)" style="cursor:pointer; font-size:14px; opacity:0.5;" title="Edit Channel">⚙️</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="padding: 10px 15px; font-size: 11px; font-weight: bold; color: #9ca3af; text-transform: uppercase; background: #f3f4f6; position:sticky; top:0;">Direct Messages</div>
            <?php foreach($all_users as $u):
                $uId   = json_encode($u['login_id']);
                $uName = json_encode($u['name']);
            ?>
                <div class="user-list-item" data-login-id="<?= htmlspecialchars($u['login_id'], ENT_QUOTES) ?>" onclick="selectUser(event, <?= htmlspecialchars($uId, ENT_QUOTES) ?>, <?= htmlspecialchars($uName, ENT_QUOTES) ?>)" style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="flex:1;">
                        <strong><?= htmlspecialchars($u['name']) ?></strong>
                        <span>@<?= htmlspecialchars($u['login_id']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if(empty($all_users)): ?>
                <div style="padding:20px; color:#999; text-align:center;">No other users found.</div>
            <?php endif; ?>
        </div>

        <!-- Chat Area -->
        <div class="chat-box" id="chatArea" style="display:none;">
            <div class="chat-header" id="chatHeader">Chatting with...</div>
            <div class="chat-messages" id="chatMessages">
                <!-- Messages populate here -->
            </div>
            <div class="chat-input" style="padding:15px; border-top:1px solid #ddd; background:#fff;">
                <form id="chatForm" style="display:flex; gap:10px; width:100%; margin:0; align-items:center;">
                    <label style="cursor:pointer; display:flex; align-items:center; justify-content:center; width:40px; height:40px; background:#f3f4f6; border-radius:50%; transition:background 0.2s;" onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'" title="Attach File">
                        <input type="file" id="chatFileInput" style="display:none;" onchange="handleFileUpload(this)">
                        <span style="font-size:20px;">📎</span>
                    </label>
                    <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off" style="flex:1; padding:12px 16px; border:1px solid #ccc; border-radius:25px; outline:none; font-size:14px;">
                    <button type="submit" style="padding:10px 24px; background:#5a2d82; color:white; border:none; border-radius:25px; cursor:pointer; font-weight:bold;">Send</button>
                </form>
            </div>
        </div>
        
        <div id="noChatSelected" style="flex:1; display:flex; align-items:center; justify-content:center; color:#999; flex-direction:column;">
            <div style="font-size: 48px; margin-bottom: 20px;">💬</div>
            <h3>Select a user to start messaging</h3>
        </div>
    </div>
</div>

<!-- Create Channel Modal -->
<div class="modal" id="channelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Create New Channel</h2>
        <form onsubmit="createChannel(event)">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px; color:#475569;">Channel Name</label>
            <input type="text" id="chan_name" required placeholder="e.g. Marketing" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px; color:#475569;">Description</label>
            <input type="text" id="chan_desc" placeholder="What is this channel for?" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px; outline:none;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('channelModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold; color:#475569;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Create Channel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Channel Modal -->
<div class="modal" id="editChannelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:400px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Edit Channel</h2>
        <form onsubmit="submitEditChannel(event)">
            <input type="hidden" id="edit_chan_id">
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px; color:#475569;">Channel Name</label>
            <input type="text" id="edit_chan_name" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px; outline:none;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px; color:#475569;">Description</label>
            <input type="text" id="edit_chan_desc" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px; outline:none;">
            
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <button type="button" onclick="deleteChannel()" style="background:#ef4444; color:white; border:none; padding:10px 15px; border-radius:6px; font-weight:bold; cursor:pointer;">Delete</button>
                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="document.getElementById('editChannelModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold; color:#475569;">Cancel</button>
                    <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentChatUser = null;
let fetchInterval = null;

function selectUser(event, loginId, name) {
    currentChatUser = loginId;
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatArea').style.display = 'flex';
    document.getElementById('chatHeader').textContent = 'Chatting with ' + name;
    document.querySelectorAll('.user-list-item').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    loadMessages();
    if(fetchInterval) clearTimeout(fetchInterval);
    scheduleNextPoll();
}

function scheduleNextPoll() {
    if (!currentChatUser) return;
    
    updateUnreadCounts(); // Also update badges while polling

    // Adaptive Polling: 3 seconds if active, 15 seconds if tab is hidden/backgrounded
    let interval = document.hidden ? 15000 : 3000;
    fetchInterval = setTimeout(() => {
        loadMessages();
        scheduleNextPoll();
    }, interval);
}

let unreadCountsStore = {};

function updateUnreadCounts() {
    fetch('controllers/chat_api.php?action=unread_counts')
    .then(res => res.json())
    .then(data => {
        if (!data.dms) return;
        document.querySelectorAll('.user-list-item').forEach(el => {
            let loginId = el.getAttribute('data-login-id');
            if (loginId) {
                let badge = el.querySelector('.unread-badge');
                let count = data.dms[loginId] ? parseInt(data.dms[loginId]) : 0;
                
                if (count > (unreadCountsStore[loginId] || 0)) {
                    playNotificationSound();
                    let senderName = el.querySelector('strong').textContent;
                    showLocalNotification(senderName, "Sent you a new message");
                }
                unreadCountsStore[loginId] = count;

                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'unread-badge';
                        badge.style = "background:#ef4444;color:white;border-radius:10px;padding:2px 6px;font-size:10px;margin-left:8px;font-weight:bold;";
                        el.appendChild(badge);
                    }
                    badge.textContent = count;
                } else {
                    if (badge) badge.remove();
                }
            }
        });
    });
}

// Initial fetch for badges
updateUnreadCounts();

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function createChannel(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'create_channel');
    fd.append('name', document.getElementById('chan_name').value);
    fd.append('description', document.getElementById('chan_desc').value);
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            document.getElementById('channelModal').style.display='none';
            // Append to list
            let list = document.getElementById('dynamicChannelsList');
            let div = document.createElement('div');
            div.className = 'user-list-item';
            div.setAttribute('onclick', `selectUser(event, '${escapeHtml(res.name)}', '${escapeHtml(res.name)}')`);
            div.innerHTML = `<strong>${escapeHtml(res.name)}</strong><span>${escapeHtml(res.description)}</span>`;
            list.appendChild(div);
        } else {
            alert(res.message);
        }
    });
}

function openEditChannelModal(id, name, desc) {
    document.getElementById('edit_chan_id').value = id;
    document.getElementById('edit_chan_name').value = name.replace('#', '');
    document.getElementById('edit_chan_desc').value = desc;
    document.getElementById('editChannelModal').style.display = 'flex';
}

function submitEditChannel(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'edit_channel');
    fd.append('id', document.getElementById('edit_chan_id').value);
    fd.append('name', document.getElementById('edit_chan_name').value);
    fd.append('description', document.getElementById('edit_chan_desc').value);
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            window.location.reload();
        } else {
            alert(res.message);
        }
    });
}

function deleteChannel() {
    if(!confirm("Are you sure you want to delete this channel? This will delete all messages inside it.")) return;
    
    let fd = new FormData();
    fd.append('action', 'delete_channel');
    fd.append('id', document.getElementById('edit_chan_id').value);
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
        if(res.status === 'success') {
            window.location.reload();
        } else {
            alert(res.message);
        }
    });
}

function loadMessages() {
    if (!currentChatUser) return;
    fetch('controllers/chat_api.php?action=fetch&partner=' + encodeURIComponent(currentChatUser))
    .then(res => res.json())
    .then(messages => {
        let box = document.getElementById('chatMessages');
        let html = '';
        let newHighestId = chatLastMsgId;
        let newIncoming = [];

        messages.forEach(msg => {
            let msgId = parseInt(msg.id);
            if (msgId > chatLastMsgId) {
                if (msgId > newHighestId) newHighestId = msgId;
                if (chatHasLoadedOnce && msg.sender_id !== '<?= $_SESSION["login_id"] ?>') {
                    newIncoming.push(msg);
                }
            }

            let type = (msg.sender_id === '<?= $_SESSION["login_id"] ?>') ? 'sent' : 'received';
            let senderNameHTML = (type === 'received' && msg.sender_name && currentChatUser.startsWith('#'))
                ? `<div style="font-size:11px;font-weight:600;margin-bottom:3px;opacity:.8;">${escapeHtml(msg.sender_name)}</div>` : '';

            let contentHTML;
            if (msg.file_path) {
                const safeUrl  = encodeURI(msg.file_path);
                const safeName = escapeHtml(msg.file_name);
                if (msg.file_type === 'image') {
                    contentHTML = `<div style="margin-bottom:4px;"><img src="${safeUrl}" style="max-width:100%;border-radius:8px;cursor:pointer;" onclick="window.open('${safeUrl}','_blank')"></div>`;
                } else {
                    contentHTML = `<div style="padding:8px 12px;background:rgba(0,0,0,.06);border-radius:6px;display:flex;align-items:center;gap:8px;">
                        <span style="font-size:20px;">📄</span>
                        <a href="${safeUrl}" target="_blank" rel="noopener" style="color:inherit;text-decoration:none;font-weight:600;word-break:break-all;">${safeName}</a>
                    </div>`;
                }
            } else {
                contentHTML = `<div>${escapeHtml(msg.message)}</div>`;
            }

            html += `<div class="msg-bubble ${type}">${senderNameHTML}
                        ${contentHTML}
                        <div class="msg-time">${escapeHtml(msg.timestamp)}</div>
                     </div>`;
        });

        if (newIncoming.length > 0) {
            playNotificationSound();
            newIncoming.forEach(msg => showLocalNotification(msg.sender_name || msg.sender_id, msg.message || 'Sent an attachment'));
        }
        
        chatLastMsgId = newHighestId;
        chatHasLoadedOnce = true;

        const isScrolledToBottom = box.scrollHeight - box.clientHeight <= box.scrollTop + 50;
        box.innerHTML = html || '<div style="text-align:center;color:var(--text-muted);margin-top:30px;">Say hello! 👋</div>';
        if (isScrolledToBottom) box.scrollTop = box.scrollHeight;
    });
}

document.getElementById('chatForm').addEventListener('submit', function(e) {
    e.preventDefault();
    if (!currentChatUser) return;
    
    let msgInput = document.getElementById('messageInput');
    let text = msgInput.value.trim();
    if (!text) return;

    let formData = new FormData();
    formData.append('action', 'send');
    formData.append('receiver', currentChatUser);
    formData.append('message', text);
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) formData.append('csrf_token', csrfMeta.content);

    fetch('controllers/chat_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(result => {
        if(result.status === 'success') {
            msgInput.value = '';
            loadMessages(); // reload immediately
        }
    });
});

function handleFileUpload(input) {
    if (!currentChatUser || !input.files[0]) return;
    
    let file = input.files[0];
    let formData = new FormData();
    formData.append('action', 'upload');
    formData.append('receiver', currentChatUser);
    formData.append('chat_file', file);
    
    let csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) formData.append('csrf_token', csrfMeta.content);
    
    // Add temporary loading bubble
    let box = document.getElementById('chatMessages');
    box.innerHTML += `<div class="msg-bubble sent" id="tempUploadBubble">
                        <div>Uploading ${file.name}...</div>
                     </div>`;
    box.scrollTop = box.scrollHeight;
    
    fetch('controllers/chat_api.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(result => {
        let temp = document.getElementById('tempUploadBubble');
        if(temp) temp.remove();
        
        if(result.status === 'success') {
            loadMessages();
        } else {
            alert("Upload Failed: " + (result.message || 'Unknown error'));
        }
    }).catch(e => {
        let temp = document.getElementById('tempUploadBubble');
        if(temp) temp.remove();
        alert("Upload Error");
    });
    
    input.value = ''; // Reset
}
</script>

<?php require_once 'includes/footer.php'; ?>


