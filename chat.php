<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';





$au_stmt = $pdo->prepare("
    SELECT login_id, name FROM users WHERE login_id != ? AND status = 'Active' 
    UNION 
    SELECT login_id, name FROM super_admins WHERE login_id != ? 
    ORDER BY name ASC
");
$au_stmt->execute([$_SESSION['login_id'], $_SESSION['login_id']]);
$all_users = $au_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch channels
$channels = $pdo->query("SELECT * FROM chat_channels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
if(empty($channels)) {
    $pdo->exec("INSERT INTO chat_channels (name, description) VALUES ('#General', 'Company-wide Broadcast'), ('#Sales', 'Lead & Client Discussion'), ('#Engineering', 'Tech & Development')");
    $channels = $pdo->query("SELECT * FROM chat_channels ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<style>
.chat-container { 
    display:flex; 
    height:calc(100vh - 220px); 
    min-height: 500px;
    background:#ffffff; 
    border-radius:24px; 
    overflow:hidden; 
    box-shadow: 0 10px 40px rgba(0,0,0,0.08); 
    border: 1px solid rgba(0,0,0,0.05);
}
.user-list { width:320px; border-right:1px solid rgba(0,0,0,0.08); background:#fdfdfd; overflow-y:auto; flex-shrink:0; display:flex; flex-direction:column; }
.user-list-header { padding: 16px 20px 8px; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:#fdfdfd; z-index:2; }
.user-list-item { 
    display:flex; align-items:center; gap:14px; padding:12px 16px; 
    margin:4px 12px; border-radius:12px; cursor:pointer; 
    transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
}
.user-list-item:hover { background:#f1f5f9; }
.user-list-item.selected { 
    background:linear-gradient(135deg, var(--primary-color), #7c3aed); 
    box-shadow: 0 4px 15px rgba(79, 102, 241, 0.25);
}
.user-list-item.selected strong { color:#ffffff; }
.user-list-item.selected span { color:rgba(255,255,255,0.8); }
.user-list-item strong { display:block; color:var(--text-heading); font-size:14.5px; font-weight:600; margin-bottom:2px; }
.user-list-item span { font-size:12px; color:var(--text-muted); display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Avatars */
.chat-avatar { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:16px; color:white; flex-shrink:0; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.avatar-channel { background: linear-gradient(135deg, #10b981, #059669); }
.avatar-user { background: linear-gradient(135deg, #3b82f6, #2563eb); }

.chat-box { flex:1; display:flex; flex-direction:column; background:#f8fafc; position:relative; }
.chat-header { 
    padding:16px 24px; 
    background:rgba(255, 255, 255, 0.9); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
    border-bottom:1px solid rgba(0,0,0,0.05); 
    color:var(--text-heading); font-weight:700; font-size:16px; 
    display:flex; align-items:center; gap:12px; z-index:10;
}
.chat-messages { flex:1; padding:24px; overflow-y:auto; display:flex; flex-direction:column; gap:16px; scroll-behavior:smooth; }
.msg-bubble { max-width:70%; padding:14px 18px; border-radius:20px; font-size:14.5px; line-height:1.5; word-break:break-word; box-shadow:0 2px 8px rgba(0,0,0,0.04); position:relative; }
.msg-bubble.sent { 
    background:linear-gradient(135deg, var(--primary-color), #7c3aed); 
    color:#fff; align-self:flex-end; border-bottom-right-radius:4px; 
}
.msg-bubble.received { 
    background:#ffffff; color:var(--text-body); align-self:flex-start; 
    border-bottom-left-radius:4px; border:1px solid rgba(0,0,0,0.05); 
}
.msg-time { font-size:11px; margin-top:6px; opacity:.75; text-align:right; font-weight:500; }

.chat-input-wrapper { padding:20px 24px; background:transparent; }
.chat-input { 
    display:flex; gap:12px; align-items:center; background:#ffffff; 
    padding:10px 14px; border-radius:99px; box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1px solid rgba(0,0,0,0.05); 
}
.chat-input input { 
    flex:1; padding:10px 16px; border:none; outline:none; background:transparent; 
    color:var(--text-body); font-size:15px; font-family:inherit; 
}
.chat-input button { 
    height:44px; width:44px; border-radius:50%; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color:#fff; border:none; 
    cursor:pointer; font-size:18px; transition:all .2s; box-shadow:0 4px 12px rgba(79,70,229,0.3); flex-shrink:0;
}
.chat-input button:hover { transform:scale(1.05); box-shadow:0 6px 16px rgba(79,70,229,0.4); }
.file-attach-btn {
    cursor:pointer; display:flex; align-items:center; justify-content:center; width:44px; height:44px; 
    background:#f1f5f9; border-radius:50%; transition:background 0.2s; color:#64748b; font-size:18px; flex-shrink:0;
}
.file-attach-btn:hover { background:#e2e8f0; color:var(--primary-color); }
</style>

<div class="content-section active" style="padding-top:0;">
    <div class="section-header" style="margin: 20px 0;">
        <h2>Corporate Communication Hub</h2>
    </div>
    
    <div class="chat-container">
        <!-- User Directory Sidebar -->
        <div class="user-list">
            <div class="user-list-header">
                Public Channels
                <?php if(hasPermission($pdo, 'moderate_chat')): ?>
                <span onclick="document.getElementById('channelModal').style.display='flex'" style="cursor:pointer; font-size:14px; color:var(--primary-color);" title="Create Channel"><i class="fas fa-plus-circle"></i></span>
                <?php endif; ?>
            </div>
            
            <div id="dynamicChannelsList">
                <?php foreach($channels as $c):
                    $cId   = json_encode($c['name']);
                    $cName = json_encode($c['name']);
                    $cDesc = htmlspecialchars($c['description'] ?? 'Discussion channel');
                ?>
                <div class="user-list-item channel-item" data-login-id="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" onclick="selectUser(event, <?= htmlspecialchars($cId, ENT_QUOTES) ?>, <?= htmlspecialchars($cName, ENT_QUOTES) ?>)">
                    <div class="chat-avatar avatar-channel">#</div>
                    <div style="flex:1; min-width:0;">
                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                        <span><?= $cDesc ?></span>
                    </div>
                    <?php if(hasPermission($pdo, 'moderate_chat')): ?>
                    <span onclick="event.stopPropagation(); openEditChannelModal(<?= $c['id'] ?>, <?= htmlspecialchars(json_encode($c['name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($c['description']), ENT_QUOTES) ?>)" style="cursor:pointer; font-size:12px; color:#cbd5e1;" title="Edit Channel"><i class="fas fa-cog"></i></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="user-list-header" style="margin-top:10px;">Direct Messages</div>
            <div id="dynamicUsersList">
                <?php foreach($all_users as $u):
                    $uId   = json_encode($u['login_id']);
                    $uName = json_encode($u['name']);
                    $initial = strtoupper(substr($u['name'], 0, 1));
                ?>
                    <div class="user-list-item" data-login-id="<?= htmlspecialchars($u['login_id'], ENT_QUOTES) ?>" onclick="selectUser(event, <?= htmlspecialchars($uId, ENT_QUOTES) ?>, <?= htmlspecialchars($uName, ENT_QUOTES) ?>)">
                        <div class="chat-avatar avatar-user"><?= $initial ?></div>
                        <div style="flex:1; min-width:0;">
                            <strong><?= htmlspecialchars($u['name']) ?></strong>
                            <span>@<?= htmlspecialchars($u['login_id']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($all_users)): ?>
                    <div style="padding:20px; color:#94a3b8; text-align:center; font-size:13px;">No other users found.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-box" id="chatArea" style="display:none;">
            <div class="chat-header" id="chatHeader">
                <div class="chat-avatar avatar-user" id="chatHeaderAvatar" style="width:36px; height:36px; font-size:14px;">?</div>
                <div>
                    <div id="chatHeaderName" style="font-weight:700; font-size:15px;">Chatting with...</div>
                    <div style="font-size:11px; color:#10b981; font-weight:600;">● Online</div>
                </div>
            </div>
            <div class="chat-messages" id="chatMessages">
                <!-- Messages populate here -->
            </div>
            <div class="chat-input-wrapper">
                <form id="chatForm" style="margin:0;">
                    <div class="chat-input">
                        <label class="file-attach-btn" title="Attach File">
                            <input type="file" id="chatFileInput" style="display:none;" onchange="handleFileUpload(this)">
                            <i class="fas fa-paperclip"></i>
                        </label>
                        <input type="text" id="messageInput" placeholder="Type your message..." autocomplete="off">
                        <button type="submit" title="Send Message"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Empty State -->
        <div id="noChatSelected" style="flex:1; display:flex; align-items:center; justify-content:center; flex-direction:column; background:linear-gradient(135deg, #f8fafc, #f1f5f9);">
            <div style="width:120px; height:120px; border-radius:50%; background:#ffffff; box-shadow:0 10px 25px rgba(0,0,0,0.05); display:flex; align-items:center; justify-content:center; margin-bottom:24px;">
                <i class="fas fa-comments" style="font-size:48px; color:var(--primary-color); opacity:0.8;"></i>
            </div>
            <h3 style="color:var(--text-heading); font-size:22px; font-weight:700; margin-bottom:8px;">Your Messages</h3>
            <p style="color:var(--text-muted); font-size:14px; font-weight:500;">Select a channel or direct message to start collaborating.</p>
        </div>
    </div>
</div>

<!-- Create Channel Modal -->
<div class="modal premium-modal" id="channelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:400px; box-shadow:0 10px 40px rgba(0,0,0,0.15);">
        <h2 style="margin-top:0; font-size:20px; font-weight:700;">Create New Channel</h2>
        <form onsubmit="createChannel(event)">
            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#475569;">Channel Name</label>
            <input type="text" id="chan_name" required placeholder="e.g. Marketing" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:20px; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            
            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#475569;">Description</label>
            <input type="text" id="chan_desc" placeholder="What is this channel for?" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:24px; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button type="button" onclick="document.getElementById('channelModal').style.display='none'" style="background:#f1f5f9; border:none; padding:10px 24px; border-radius:99px; cursor:pointer; font-weight:600; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                <button type="submit" class="premium-btn">Create Channel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Channel Modal -->
<div class="modal premium-modal" id="editChannelModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; backdrop-filter:blur(8px);">
    <div class="modal-content" style="background:white; padding:32px; border-radius:16px; width:400px; box-shadow:0 10px 40px rgba(0,0,0,0.15);">
        <h2 style="margin-top:0; font-size:20px; font-weight:700;">Edit Channel</h2>
        <form onsubmit="submitEditChannel(event)">
            <input type="hidden" id="edit_chan_id">
            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#475569;">Channel Name</label>
            <input type="text" id="edit_chan_name" required style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:20px; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            
            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:14px; color:#475569;">Description</label>
            <input type="text" id="edit_chan_desc" style="width:100%; padding:12px; border:1px solid #cbd5e1; border-radius:8px; margin-bottom:24px; outline:none; transition:border 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='#cbd5e1'">
            
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <button type="button" onclick="deleteChannel()" style="background:#fee2e2; border:none; padding:10px 20px; border-radius:99px; cursor:pointer; font-weight:600; color:#ef4444; transition:background 0.2s;" onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">Delete</button>
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="document.getElementById('editChannelModal').style.display='none'" style="background:#f1f5f9; border:none; padding:10px 24px; border-radius:99px; cursor:pointer; font-weight:600; color:#475569; transition:background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Cancel</button>
                    <button type="submit" class="premium-btn">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentChatUser = null;
let fetchInterval = null;
let chatLastMsgId = 0;
let chatHasLoadedOnce = false;

function selectUser(event, loginId, name) {
    currentChatUser = loginId;
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatArea').style.display = 'flex';
    
    // Update Header Name and Avatar
    document.getElementById('chatHeaderName').textContent = name;
    let isChannel = loginId.startsWith('#');
    let avatarEl = document.getElementById('chatHeaderAvatar');
    if(isChannel) {
        avatarEl.className = 'chat-avatar avatar-channel';
        avatarEl.textContent = '#';
    } else {
        avatarEl.className = 'chat-avatar avatar-user';
        avatarEl.textContent = name.charAt(0).toUpperCase();
    }

    document.querySelectorAll('.user-list-item').forEach(el => el.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    loadMessages();
    if(fetchInterval) clearTimeout(fetchInterval);
    scheduleNextPoll();
}

function scheduleNextPoll() {
    if (!currentChatUser) return;
    
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
        document.querySelectorAll('.user-list-item').forEach(el => {
            let loginId = el.getAttribute('data-login-id');
            if (loginId) {
                let badge = el.querySelector('.unread-badge');
                let count = 0;
                if (loginId.startsWith('#')) {
                    count = (data.channels && data.channels[loginId]) ? parseInt(data.channels[loginId]) : 0;
                } else {
                    count = (data.dms && data.dms[loginId]) ? parseInt(data.dms[loginId]) : 0;
                }
                
                if (count > (unreadCountsStore[loginId] || 0) && currentChatUser !== loginId) {
                    if(typeof playNotificationSound === 'function') playNotificationSound();
                    let senderName = el.querySelector('strong').textContent;
                    if(typeof showLocalNotification === 'function') showLocalNotification(senderName, "New message received");
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

function pollBadges() {
    updateUnreadCounts();
    let interval = document.hidden ? 15000 : 3000;
    setTimeout(pollBadges, interval);
}

// Initial fetch and start global badge polling
pollBadges();

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
            div.className = 'user-list-item channel-item';
            div.setAttribute('data-login-id', escapeHtml(res.name));
            div.setAttribute('onclick', `selectUser(event, '${escapeHtml(res.name)}', '${escapeHtml(res.name)}')`);
            div.innerHTML = `<div style="flex:1;"><strong>${escapeHtml(res.name)}</strong><span>${escapeHtml(res.description)}</span></div>`;
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


