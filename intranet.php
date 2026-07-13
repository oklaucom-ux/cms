<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="content-section active">
    <div class="section-header">
        <h2> 📣 Company Hub </h2>
        <button class="add-button" onclick="document.getElementById('postModal').style.display='flex'" style="background:#2563eb;">✍️ Write a Post</button>
    </div>

    <div style="max-width:800px; margin:0 auto;" id="feedContainer">
        <!-- Feed loaded via JS -->
    </div>
</div>

<!-- Post Modal -->
<div class="modal" id="postModal">
    <div class="modal-content" style="width:600px;">
        <h2>Create Post</h2>
        <form id="postForm" onsubmit="submitPost(event)">
            <?php if(in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <label>Post Type</label>
            <select id="post_type" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-top:5px; margin-bottom:15px; outline:none;">
                <option value="General">General Update</option>
                <option value="Announcement">📣 Official Announcement (Notifies Everyone)</option>
            </select>
            <?php else: ?>
            <input type="hidden" id="post_type" value="General">
            <?php endif; ?>
            
            <textarea id="post_content" rows="6" placeholder="What's happening in your department?" style="width:100%; padding:15px; border:1px solid #cbd5e1; border-radius:8px; font-size:16px; outline:none; resize:none;"></textarea>
            
            <div style="margin-top:20px; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('postModal').style.display='none'" style="background:#f1f5f9; color:#475569; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" class="add-button" style="background:#2563eb;">Post to Feed</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000; }
.modal-content { background:white; padding:30px; border-radius:12px; box-shadow:0 10px 25px rgba(0,0,0,0.1); }
</style>

<script>
function loadFeed() {
    fetch('controllers/intranet_api.php?action=list&t=' + Date.now())
    .then(r => r.text())
    .then(text => {
        try {
            let res = JSON.parse(text);
            if (res.status !== 'success') {
                document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; text-align:center; color:#ef4444; background:#fef2f2; border-radius:8px; border:1px solid #fecaca;"><b>Failed to load feed:</b> ${res.message || 'Unknown error'}</div>`;
                return;
            }
            
            let h = '';
            if (res.data && res.data.length > 0) {
                res.data.forEach(p => {
                    let isAnnounce = p.post_type === 'Announcement';
                    let bg = isAnnounce ? 'linear-gradient(to right, #fef3c7, #fffbeb)' : 'white';
                    let border = isAnnounce ? '' : 'border:1px solid #e2e8f0;';
                    let badge = isAnnounce ? `<span style="background:#f59e0b; color:white; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:bold; margin-left:10px;">Official Announcement</span>` : '';
                    
                    let likeColor = p.liked_by_me > 0 ? '#2563eb' : '#64748b';
                    
                    let commentsHtml = '';
                    if (p.comments) {
                        p.comments.forEach(c => {
                            commentsHtml += `<div style="background:#f8fafc; padding:10px 15px; border-radius:8px; margin-bottom:5px; font-size:14px;">
                                <strong style="color:#0f172a;">${c.author_name}</strong>: <span style="color:#475569;">${c.content}</span>
                            </div>`;
                        });
                    }
                    
                    h += `<div style="background:${bg}; ${border} padding:25px; border-radius:12px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:40px; height:40px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold; color:#475569;">${p.author_name ? p.author_name.charAt(0) : '?'}</div>
                                    <div>
                                        <div style="font-weight:bold; color:#0f172a; font-size:16px;">${p.author_name} ${badge}</div>
                                        <div style="color:#64748b; font-size:12px;">${p.author_role} • ${new Date(p.created_at).toLocaleString()}</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="font-size:16px; color:#1e293b; line-height:1.5; margin-bottom:20px; white-space:pre-wrap;">${p.content}</div>
                            
                            <div style="display:flex; gap:20px; border-top:1px solid #e2e8f0; padding-top:15px; margin-bottom:15px;">
                                <button onclick="toggleLike(${p.id})" style="background:none; border:none; cursor:pointer; color:${likeColor}; font-weight:bold; font-size:14px; display:flex; align-items:center; gap:5px;">
                                    👍 Like (${p.likes_count})
                                </button>
                                <button style="background:none; border:none; color:#64748b; font-weight:bold; font-size:14px; display:flex; align-items:center; gap:5px;">
                                    💬 Comment (${p.comments_count})
                                </button>
                            </div>
                            
                            <div style="margin-top:10px;">${commentsHtml}
                                <form onsubmit="submitComment(event, ${p.id})" style="display:flex; gap:10px; margin-top:10px;">
                                    <input type="text" id="comment_${p.id}" placeholder="Write a comment..." style="flex:1; padding:10px 15px; border:1px solid #cbd5e1; border-radius:99px; outline:none;" required>
                                    <button type="submit" style="background:#2563eb; color:white; border:none; padding:10px 20px; border-radius:99px; cursor:pointer; font-weight:bold;">Post</button>
                                </form>
                            </div>
                          </div>`;
                });
            }
            document.getElementById('feedContainer').innerHTML = h || '<p style="text-align:center; color:#94a3b8; padding:40px;">No posts yet. Start the conversation!</p>';
        } catch (e) {
            document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; color:#ef4444; background:#fef2f2; border:1px solid #fecaca; border-radius:8px;">
                <b>System Error:</b> Could not parse server response.<br><br>
                <div style="font-family:monospace; font-size:12px; background:#fff; padding:10px; border:1px solid #ddd; max-height:200px; overflow-y:auto;">
                    ${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}
                </div>
            </div>`;
        }
    })
    .catch(err => {
        document.getElementById('feedContainer').innerHTML = `<div style="padding:40px; text-align:center; color:#ef4444;"><b>Network Error:</b> Failed to connect to server.</div>`;
    });
}
loadFeed();

function submitPost(e) {
    e.preventDefault();
    let fd = new FormData();
    fd.append('action', 'post');
    fd.append('content', document.getElementById('post_content').value);
    fd.append('post_type', document.getElementById('post_type').value);
    
    fetch('controllers/intranet_api.php', {method:'POST', body:fd})
    .then(r=>r.json()).then((res)=>{
        if(res.status === 'success') {
            document.getElementById('postModal').style.display = 'none';
            document.getElementById('post_content').value = '';
            loadFeed();
        } else {
            Swal.fire('Error', res.message || 'Failed to post', 'error');
        }
    }).catch(err => {
        Swal.fire('Error', 'Network or server error', 'error');
    });
}

function toggleLike(id) {
    let fd = new FormData(); fd.append('action', 'like'); fd.append('post_id', id);
    fetch('controllers/intranet_api.php', {method:'POST', body:fd}).then(()=>loadFeed());
}

function submitComment(e, id) {
    e.preventDefault();
    let fd = new FormData(); fd.append('action', 'comment'); fd.append('post_id', id); fd.append('content', document.getElementById('comment_'+id).value);
    fetch('controllers/intranet_api.php', {method:'POST', body:fd}).then(()=>loadFeed());
}
</script>

<?php require_once 'includes/footer.php'; ?>
