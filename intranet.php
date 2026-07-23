<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_intranet');
?>

<div class="content-section active" style="padding-top:0;">
    <!-- Hero Banner -->
    <div style="background: linear-gradient(135deg, #4f46e5, #ec4899); border-radius: 0 0 24px 24px; padding: 40px 40px 80px 40px; margin: -20px -20px 20px -20px; color: white; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(79, 70, 229, 0.2);">
        <div style="position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin: 0 0 10px 0; font-size: 32px; font-weight: 800; letter-spacing: -0.5px;">Company Hub</h1>
                <p style="margin: 0; font-size: 16px; opacity: 0.9;">Connecting the team, sharing ideas, and celebrating wins.</p>
            </div>
            <button onclick="document.getElementById('postModal').style.display='flex'" style="background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.4); color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-edit"></i> Write a Post
            </button>
        </div>
        <!-- Decorative bg elements -->
        <div style="position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; background: rgba(255,255,255,0.1); border-radius: 50%; filter: blur(30px);"></div>
        <div style="position: absolute; left: 20%; bottom: -100px; width: 300px; height: 300px; background: rgba(0,0,0,0.1); border-radius: 50%; filter: blur(40px);"></div>
    </div>

    <div style="display: flex; gap: 30px; margin-top: -60px; position: relative; z-index: 5; padding: 0 20px;">
        
        <!-- Left Sidebar (Quick Links / Widgets) -->
        <div style="width: 280px; flex-shrink: 0; display: flex; flex-direction: column; gap: 20px;">
            <div class="glass-card" style="padding: 24px; border-radius: 16px;">
                <h3 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-bolt" style="color:#f59e0b; margin-right:6px;"></i> Quick Links</h3>
                <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px;">
                    <li><a href="documents.php" style="text-decoration: none; color: var(--text-heading); font-weight: 600; display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.03)'" onmouseout="this.style.background='transparent'"><i class="fas fa-folder-open" style="color: #3b82f6; width: 20px;"></i> Employee Handbook</a></li>
                    <li><a href="policies.php" style="text-decoration: none; color: var(--text-heading); font-weight: 600; display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.03)'" onmouseout="this.style.background='transparent'"><i class="fas fa-shield-alt" style="color: #10b981; width: 20px;"></i> IT Policies</a></li>
                    <li><a href="benefits.php" style="text-decoration: none; color: var(--text-heading); font-weight: 600; display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 8px; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.03)'" onmouseout="this.style.background='transparent'"><i class="fas fa-heartbeat" style="color: #ec4899; width: 20px;"></i> Health Benefits</a></li>
                </ul>
            </div>

            <div class="glass-card" style="padding: 24px; border-radius: 16px;">
                <h3 style="margin: 0 0 15px 0; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);"><i class="fas fa-birthday-cake" style="color:#8b5cf6; margin-right:6px;"></i> Celebrations</h3>
                <div style="background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.2); padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: #8b5cf6; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 18px;">🎉</div>
                    <div>
                        <div style="font-weight: 700; color: var(--text-heading); font-size: 14px;">No upcoming birthdays</div>
                        <div style="font-size: 12px; color: var(--text-muted);">Check back later this month!</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Feed -->
        <div style="flex: 1; max-width: 700px; display: flex; flex-direction: column; gap: 24px;" id="feedContainer">
            <!-- Feed loaded via JS -->
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px;"></i>
                <div>Loading Feed...</div>
            </div>
        </div>

        <!-- Right Spacer for balance -->
        <div style="width: 100px; flex-shrink: 0;"></div>
    </div>
</div>

<!-- Post Modal -->
<div class="modal premium-modal" id="postModal">
    <div class="modal-content" style="width: 550px; background: var(--bg-card); padding: 32px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.2);">
        <h2 style="margin: 0 0 20px 0; color: var(--text-heading); font-size: 22px; font-weight: 800;">Create a Post</h2>
        <form id="postForm" onsubmit="submitPost(event)">
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 13px;">Post Type</label>
                <select id="post_type" style="width: 100%; padding: 12px 16px; border: 1px solid var(--border-card); border-radius: 10px; background: var(--input-bg); color: var(--text-body); font-size: 14px; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-card)'">
                    <option value="General">General Update</option>
                    <option value="Announcement">📣 Official Announcement (Notifies Everyone)</option>
                </select>
            </div>
            <?php else: ?>
            <input type="hidden" id="post_type" value="General">
            <?php endif; ?>
            
            <div style="margin-bottom: 24px;">
                <textarea id="post_content" rows="5" placeholder="Share something with the team..." style="width: 100%; padding: 16px; border: 1px solid var(--border-card); border-radius: 12px; background: var(--input-bg); color: var(--text-heading); font-size: 16px; outline: none; resize: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='var(--primary-color)'" onblur="this.style.borderColor='var(--border-card)'"></textarea>
            </div>
            
            <div style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="document.getElementById('postModal').style.display='none'" style="background: rgba(0,0,0,0.05); color: var(--text-heading); border: none; padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 600; transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.1)'" onmouseout="this.style.background='rgba(0,0,0,0.05)'">Cancel</button>
                <button type="submit" class="premium-btn">Post to Hub</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); align-items: center; justify-content: center; z-index: 1000; }
.feed-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
.feed-card:hover { transform: translateY(-2px); box-shadow: 0 15px 35px rgba(0,0,0,0.06); }
[data-theme="dark"] .feed-card:hover { box-shadow: 0 15px 35px rgba(0,0,0,0.3); }
.comment-input-wrap { position: relative; }
.comment-input-wrap input { width: 100%; padding: 12px 16px 12px 40px; border: 1px solid var(--border-card); border-radius: 99px; background: var(--input-bg); color: var(--text-body); font-size: 14px; outline: none; transition: all 0.2s; }
.comment-input-wrap input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
.comment-input-wrap i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; }
.comment-input-wrap button { position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: var(--primary-color); color: white; border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: transform 0.2s; }
.comment-input-wrap button:hover { transform: translateY(-50%) scale(1.05); }
</style>

<script>
function loadFeed() {
    fetch('controllers/intranet_api.php?action=list&t=' + Date.now())
    .then(r => r.text())
    .then(text => {
        try {
            let res = JSON.parse(text);
            if (res.status !== 'success') {
                document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; text-align:center; color:#ef4444; background:#fef2f2; border-radius:12px; border:1px solid #fecaca;"><b>Failed to load feed:</b> ${res.message || 'Unknown error'}</div>`;
                return;
            }
            
            let h = '';
            if (res.data && res.data.length > 0) {
                res.data.forEach(p => {
                    let isAnnounce = p.post_type === 'Announcement';
                    let extraStyles = isAnnounce ? 'border: 2px solid #f59e0b; box-shadow: 0 10px 30px rgba(245, 158, 11, 0.15);' : 'border: 1px solid var(--border-card);';
                    let badge = isAnnounce ? `<span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; margin-left: 12px; box-shadow: 0 2px 6px rgba(245, 158, 11, 0.3);"><i class="fas fa-bullhorn" style="margin-right:4px;"></i> Announcement</span>` : '';
                    
                    let likeColor = p.liked_by_me > 0 ? '#4f46e5' : 'var(--text-muted)';
                    let likeIcon = p.liked_by_me > 0 ? 'fas fa-heart' : 'far fa-heart';
                    
                    let commentsHtml = '';
                    if (p.comments && p.comments.length > 0) {
                        commentsHtml = `<div style="margin-top: 20px; display: flex; flex-direction: column; gap: 12px; padding-top: 16px; border-top: 1px dashed var(--border-card);">`;
                        p.comments.forEach(c => {
                            let authorInitial = c.author_name ? c.author_name.charAt(0) : '?';
                            commentsHtml += `
                                <div style="display: flex; gap: 12px;">
                                    <div style="width: 32px; height: 32px; background: rgba(79, 70, 229, 0.1); color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 13px; flex-shrink: 0;">${authorInitial}</div>
                                    <div style="background: var(--bg-hover); padding: 10px 14px; border-radius: 0 12px 12px 12px; font-size: 13.5px; border: 1px solid var(--border-card);">
                                        <strong style="color: var(--text-heading); display: block; margin-bottom: 2px;">${c.author_name}</strong>
                                        <span style="color: var(--text-body); line-height: 1.4;">${c.content}</span>
                                    </div>
                                </div>`;
                        });
                        commentsHtml += `</div>`;
                    }
                    
                    h += `<div class="glass-card feed-card" style="padding: 24px; border-radius: 16px; ${extraStyles} background: var(--bg-card); position: relative; overflow: hidden;">
                            ${isAnnounce ? '<div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, #f59e0b, #d97706);"></div>' : ''}
                            
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                                <div style="display: flex; align-items: center; gap: 14px;">
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: white; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);">${p.author_name ? p.author_name.charAt(0) : '?'}</div>
                                    <div>
                                        <div style="display: flex; align-items: center;">
                                            <div style="font-weight: 800; color: var(--text-heading); font-size: 16px;">${p.author_name}</div>
                                            ${badge}
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 12.5px; margin-top: 2px; font-weight: 500;">${p.author_role} • ${new Date(p.created_at).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'})}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="font-size: 15.5px; color: var(--text-heading); line-height: 1.6; margin-bottom: 24px; white-space: pre-wrap;">${p.content}</div>
                            
                            <div style="display: flex; gap: 10px; border-top: 1px solid var(--border-card); padding-top: 16px;">
                                <button onclick="toggleLike(${p.id})" style="background: ${p.liked_by_me > 0 ? 'rgba(79, 70, 229, 0.1)' : 'transparent'}; border: none; cursor: pointer; color: ${likeColor}; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; transition: all 0.2s;">
                                    <i class="${likeIcon}"></i> ${p.likes_count} Likes
                                </button>
                                <button style="background: transparent; border: none; color: var(--text-muted); font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; pointer-events: none;">
                                    <i class="far fa-comment"></i> ${p.comments_count} Comments
                                </button>
                            </div>
                            
                            ${commentsHtml}
                            
                            <form onsubmit="submitComment(event, ${p.id})" style="margin-top: 16px;">
                                <div class="comment-input-wrap">
                                    <i class="far fa-comment-dots"></i>
                                    <input type="text" id="comment_${p.id}" placeholder="Write a comment..." required>
                                    <button type="submit"><i class="fas fa-paper-plane" style="position:static; transform:none; color:white;"></i></button>
                                </div>
                            </form>
                          </div>`;
                });
            }
            document.getElementById('feedContainer').innerHTML = h || `
                <div class="glass-card" style="padding: 60px 40px; text-align: center; border-radius: 16px; border: 1px dashed var(--border-card);">
                    <div style="width: 80px; height: 80px; background: rgba(79, 70, 229, 0.1); color: #4f46e5; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px auto;">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="margin: 0 0 10px 0; color: var(--text-heading); font-size: 18px;">Welcome to the Hub</h3>
                    <p style="color: var(--text-muted); font-size: 14px; max-width: 300px; margin: 0 auto;">No posts yet. Be the first to share an update or announcement with the team!</p>
                </div>`;
        } catch (e) {
            document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; color:#ef4444; background:#fef2f2; border:1px solid #fecaca; border-radius:12px;">
                <b>System Error:</b> Could not parse server response.<br><br>
                <div style="font-family:monospace; font-size:12px; background:#fff; padding:10px; border:1px solid #ddd; max-height:200px; overflow-y:auto;">
                    ${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}
                </div>
            </div>`;
        }
    })
    .catch(err => {
        document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; text-align:center; color:#ef4444; background: #fef2f2; border-radius: 12px; border: 1px solid #fecaca;"><b>Network Error:</b> Failed to connect to server.</div>`;
    });
}
loadFeed();

function submitPost(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'post');
    fd.append('content', document.getElementById('post_content').value);
    fd.append('post_type', document.getElementById('post_type').value);
    
    // Add CSRF Token
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    let submitBtn = e.target.querySelector('button[type="submit"]');
    let originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';
    submitBtn.disabled = true;
    
    fetch('controllers/intranet_api.php', {method:'POST', body:fd})
    .then(r=>r.json()).then((res)=>{
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if(res.status === 'success') {
            document.getElementById('postModal').style.display = 'none';
            document.getElementById('post_content').value = '';
            loadFeed();
            
            // Show toast notification
            Swal.fire({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                icon: 'success',
                title: 'Posted successfully'
            });
        } else {
            Swal.fire('Error', res.message || 'Failed to post', 'error');
        }
    }).catch(err => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        Swal.fire('Error', 'Network or server error', 'error');
    });
}

function toggleLike(id) {
    let fd = new FormData(); fd.append('action', 'like'); fd.append('post_id', id);
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    fetch('controllers/intranet_api.php', {method:'POST', body:fd}).then(()=>loadFeed());
}

function submitComment(e, id) {
    e.preventDefault();
    let fd = new FormData(); 
    fd.append('action', 'comment'); 
    fd.append('post_id', id); 
    fd.append('content', document.getElementById('comment_'+id).value);
    
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if(csrfMeta) fd.append('csrf_token', csrfMeta.content);
    
    let btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.style.opacity = '0.7';
    
    fetch('controllers/intranet_api.php', {method:'POST', body:fd}).then(()=>{
        loadFeed();
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
