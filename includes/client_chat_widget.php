<!-- Client Chat Widget -->
<style>
#clientChatBtn {
    position: fixed; bottom: 24px; right: 24px; width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
    color: white; border: none; box-shadow: 0 4px 16px rgba(79, 70, 229, 0.4);
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-size: 24px; transition: transform 0.2s; z-index: 999;
}
#clientChatBtn:hover { transform: scale(1.05); box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5); }
#clientChatBtn .unread-badge {
    position: absolute; top: -2px; right: -2px; background: #ef4444; color: white;
    font-size: 11px; font-weight: bold; width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; display:none; border:2px solid #fff;
}
#clientChatWindow {
    position: fixed; bottom: 95px; right: 24px; width: 360px; height: 500px;
    background: #fff; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    display: flex; flex-direction: column; overflow: hidden; z-index: 998;
    transform: translateY(20px); opacity: 0; pointer-events: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 1px solid rgba(0,0,0,0.05);
}
#clientChatWindow.open { transform: translateY(0); opacity: 1; pointer-events: auto; }

#chatWidgetHeader {
    padding: 16px; background: var(--bg-card); border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 12px;
}
#chatWidgetContactList {
    flex: 1; overflow-y: auto; background: #fdfdfd; display: flex; flex-direction: column;
}
.chat-contact-item {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid rgba(0,0,0,0.03);
    cursor: pointer; transition: background 0.2s;
}
.chat-contact-item:hover { background: #f1f5f9; }
.chat-contact-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }

#chatWidgetThread {
    flex: 1; display: none; flex-direction: column; background: #f8fafc;
}
#chatWidgetThreadHeader {
    padding: 12px 16px; background: #fff; border-bottom: 1px solid rgba(0,0,0,0.05);
    display: flex; align-items: center; gap: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
#chatWidgetBackBtn {
    background: transparent; border: none; cursor: pointer; color: var(--text-muted); padding: 4px; border-radius: 4px;
}
#chatWidgetBackBtn:hover { background: rgba(0,0,0,0.05); color: var(--text-heading); }
#chatWidgetMessages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 12px; }
.cw-msg { max-width: 85%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.4; word-break: break-word; }
.cw-msg.sent { background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.cw-msg.received { background: white; color: var(--text-body); align-self: flex-start; border-bottom-left-radius: 4px; border: 1px solid rgba(0,0,0,0.05); }

#chatWidgetInputArea {
    padding: 12px; background: #fff; border-top: 1px solid rgba(0,0,0,0.05); display: flex; gap: 8px; align-items: center;
}
#chatWidgetInputArea input {
    flex: 1; padding: 10px 14px; border: 1px solid rgba(0,0,0,0.1); border-radius: 99px; outline: none; font-size: 14px;
}
#chatWidgetSendBtn, #chatWidgetAttachBtn {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); color: white; border: none; border-radius: 50%;
    width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px;
}
#chatWidgetAttachBtn { background: transparent; color: var(--text-muted); }
#chatWidgetAttachBtn:hover { color: var(--primary-color); background: rgba(0,0,0,0.05); }
</style>

<button id="clientChatBtn" onclick="toggleClientChat()">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
    <div id="cwUnreadTotal" class="unread-badge">0</div>
</button>

<div id="clientChatWindow">
    <div id="cwContactListView" style="display: flex; flex-direction: column; height: 100%;">
        <div id="chatWidgetHeader">
            <div>
                <strong style="display:block; font-size: 15px;">Support Team</strong>
                <span style="font-size: 12px; color: var(--text-muted);">We typically reply in a few minutes</span>
            </div>
        </div>
        <div id="chatWidgetContactList">
            <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">Loading support contacts...</div>
        </div>
    </div>
    
    <div id="chatWidgetThread">
        <div id="chatWidgetThreadHeader">
            <button id="chatWidgetBackBtn" onclick="closeChatThread()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
            </button>
            <div class="chat-contact-avatar" id="cwActiveAvatar" style="width: 32px; height: 32px; font-size: 13px;"></div>
            <div>
                <strong style="display:block; font-size: 14px;" id="cwActiveName">...</strong>
                <span style="font-size: 11px; color: #10b981; font-weight: 600;">Online</span>
            </div>
        </div>
        <div id="chatWidgetMessages"></div>
        <div style="padding:4px 12px; font-size:11px; color:var(--text-muted); display:none;" id="cwUploadStatus">Uploading...</div>
        <div id="chatWidgetInputArea">
            <label id="chatWidgetAttachBtn" for="cwFileInput">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
            </label>
            <input type="file" id="cwFileInput" style="display:none;" onchange="cwUploadFile()">
            <input type="text" id="cwMessageInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') cwSendMessage()">
            <button id="chatWidgetSendBtn" onclick="cwSendMessage()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:-2px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            </button>
        </div>
    </div>
</div>

<script>
let cwActivePartner = null;
let cwPollInterval = null;
let cwContacts = [];
let cwLastMsgId = 0;

function toggleClientChat() {
    const win = document.getElementById('clientChatWindow');
    win.classList.toggle('open');
    if (win.classList.contains('open') && cwContacts.length === 0) {
        cwLoadContacts();
    }
}

function cwLoadContacts() {
    fetch('controllers/chat_api_client_contacts.php')
        .then(r => r.json())
        .then(data => {
            cwContacts = data;
            const list = document.getElementById('chatWidgetContactList');
            list.innerHTML = '';
            if (data.length === 0) {
                list.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-muted); font-size:13px;">No support staff assigned yet.</div>';
                return;
            }
            data.forEach(c => {
                const initial = c.name.charAt(0).toUpperCase();
                const div = document.createElement('div');
                div.className = 'chat-contact-item';
                div.onclick = () => openChatThread(c.login_id, c.name, initial);
                div.innerHTML = `
                    <div class="chat-contact-avatar">${initial}</div>
                    <div style="flex:1;">
                        <strong style="display:block; font-size:14px; color:var(--text-heading);">${escapeHtml(c.name)}</strong>
                        <span style="font-size:12px; color:var(--text-muted);">${escapeHtml(c.role)}</span>
                    </div>
                    <div class="unread-badge" id="cwBadge-${c.login_id}" style="background:#ef4444; color:white; font-size:10px; font-weight:bold; width:18px; height:18px; border-radius:50%; display:none; align-items:center; justify-content:center;">0</div>
                `;
                list.appendChild(div);
            });
            cwPollUnread();
        });
}

function openChatThread(loginId, name, initial) {
    cwActivePartner = loginId;
    document.getElementById('cwContactListView').style.display = 'none';
    document.getElementById('chatWidgetThread').style.display = 'flex';
    document.getElementById('cwActiveName').innerText = name;
    document.getElementById('cwActiveAvatar').innerText = initial;
    
    const badge = document.getElementById('cwBadge-' + loginId);
    if(badge) badge.style.display = 'none';
    
    document.getElementById('chatWidgetMessages').innerHTML = '<div style="text-align:center; margin-top:20px; color:#94a3b8; font-size:13px;">Loading...</div>';
    
    cwLoadMessages();
    if(cwPollInterval) clearInterval(cwPollInterval);
    cwPollInterval = setInterval(cwLoadMessages, 3000);
}

function closeChatThread() {
    cwActivePartner = null;
    document.getElementById('chatWidgetThread').style.display = 'none';
    document.getElementById('cwContactListView').style.display = 'flex';
    if(cwPollInterval) clearInterval(cwPollInterval);
    cwPollUnread();
    cwPollInterval = setInterval(cwPollUnread, 5000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function cwLoadMessages() {
    if(!cwActivePartner) return;
    fetch(`controllers/chat_api.php?action=fetch&partner=${encodeURIComponent(cwActivePartner)}`)
        .then(r => r.json())
        .then(msgs => {
            const box = document.getElementById('chatWidgetMessages');
            let html = '';
            let newHighest = cwLastMsgId;
            msgs.forEach(m => {
                const isMe = (m.sender_id === cwActivePartner) ? false : true;
                let c = isMe ? 'sent' : 'received';
                if(m.id > newHighest) newHighest = m.id;
                
                let msgContent = escapeHtml(m.message);
                if (m.file_path) {
                    if (m.file_type === 'image') {
                        msgContent = `<img src="${escapeHtml(m.file_path)}" style="max-width:100%; border-radius:8px; cursor:pointer;" onclick="window.open('${escapeHtml(m.file_path)}', '_blank')"><br><span style="font-size:11px;">${escapeHtml(m.file_name)}</span>`;
                    } else {
                        msgContent = `<a href="${escapeHtml(m.file_path)}" target="_blank" style="color:inherit; text-decoration:underline;">?? ${escapeHtml(m.file_name)}</a>`;
                    }
                }
                
                let time = new Date(m.timestamp).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
                html += `
                    <div class="cw-msg ${c}">
                        <div>${msgContent}</div>
                        <div class="msg-time">${time}</div>
                    </div>
                `;
            });
            
            if(html === '') {
                html = '<div style="text-align:center; margin-top:20px; color:#94a3b8; font-size:13px;">Say hello! ??</div>';
            }
            
            const needsScroll = (box.innerHTML === '' || box.innerHTML.includes('Loading...') || msgs.length > 0 && msgs[msgs.length-1].id > cwLastMsgId);
            box.innerHTML = html;
            cwLastMsgId = newHighest;
            if(needsScroll) box.scrollTop = box.scrollHeight;
        });
}

function cwSendMessage() {
    const input = document.getElementById('cwMessageInput');
    const msg = input.value.trim();
    if(!msg || !cwActivePartner) return;
    
    let fd = new FormData();
    fd.append('action', 'send');
    fd.append('receiver', cwActivePartner);
    fd.append('message', msg);
    
    input.value = '';
    
    const box = document.getElementById('chatWidgetMessages');
    const time = new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    box.innerHTML += `
        <div class="cw-msg sent" style="opacity:0.7">
            <div>${escapeHtml(msg)}</div>
            <div class="msg-time">${time}</div>
        </div>
    `;
    box.scrollTop = box.scrollHeight;
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            cwLoadMessages();
        });
}

function cwUploadFile() {
    const file = document.getElementById('cwFileInput').files[0];
    if(!file || !cwActivePartner) return;
    
    let fd = new FormData();
    fd.append('action', 'upload');
    fd.append('receiver', cwActivePartner);
    fd.append('chat_file', file);
    
    document.getElementById('cwUploadStatus').style.display = 'block';
    
    fetch('controllers/chat_api.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            document.getElementById('cwUploadStatus').style.display = 'none';
            if(res.status === 'success') {
                cwLoadMessages();
            } else {
                alert(res.message || 'Upload failed');
            }
        });
}

function cwPollUnread() {
    if (cwActivePartner) return;
    fetch('controllers/chat_api.php?action=unread_counts')
        .then(r => r.json())
        .then(data => {
            let total = 0;
            if (data.dms) {
                for (let sender in data.dms) {
                    let count = data.dms[sender];
                    total += count;
                    let badge = document.getElementById('cwBadge-' + sender);
                    if (badge) {
                        badge.innerText = count > 99 ? '99+' : count;
                        badge.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            }
            const mainBadge = document.getElementById('cwUnreadTotal');
            if (mainBadge) {
                mainBadge.innerText = total > 99 ? '99+' : total;
                mainBadge.style.display = total > 0 ? 'flex' : 'none';
            }
        });
}

setTimeout(() => {
    cwLoadContacts();
    cwPollInterval = setInterval(cwPollUnread, 5000);
}, 1000);
</script>
