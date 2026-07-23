<?php
// meetings.php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_meetings');

// Fetch meetings
$stmt = $pdo->prepare("SELECT m.*, u.full_name as host_name FROM meetings m LEFT JOIN users u ON m.host_id = u.login_id ORDER BY m.scheduled_time DESC");
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 style="margin: 0; color: var(--text-heading); font-size: 24px; font-weight: 700;">Virtual Meetings</h2>
            <p style="margin: 4px 0 0 0; color: var(--text-muted); font-size: 14px;">Schedule and join team video calls directly within the platform.</p>
        </div>
        <button onclick="openMeetingModal()" class="add-button" style="background: var(--primary-color); color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-video"></i> New Meeting
        </button>
    </div>

    <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 24px;">
        <!-- Meeting List / Active Meeting Area -->
        <div id="mainMeetingArea" style="background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-card); overflow: hidden; display: flex; flex-direction: column;">
            
            <div id="meetingListContainer" style="padding: 20px;">
                <?php if (count($meetings) === 0): ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-video-slash" style="font-size: 48px; color: var(--text-muted); opacity: 0.5; margin-bottom: 16px;"></i>
                        <p style="color: var(--text-muted); font-size: 16px;">No meetings scheduled yet.</p>
                        <button onclick="openMeetingModal()" class="add-button" style="margin-top: 16px; background: transparent; color: var(--primary-color); border: 1px solid var(--primary-color); padding: 8px 16px; border-radius: 6px; cursor: pointer;">Schedule First Meeting</button>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
                        <?php foreach($meetings as $m): ?>
                            <div style="border: 1px solid var(--border-card); border-radius: 10px; padding: 16px; background: var(--bg-main); transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.05)'" onmouseout="this.style.transform='none'; this.style.boxShadow='none'">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                    <h3 style="margin: 0; font-size: 16px; color: var(--text-heading); font-weight: 600;"><?= htmlspecialchars($m['title']) ?></h3>
                                    <span style="font-size: 12px; padding: 4px 8px; border-radius: 12px; background: <?= $m['status'] == 'Live' ? '#fee2e2' : '#e0e7ff' ?>; color: <?= $m['status'] == 'Live' ? '#ef4444' : '#4f46e5' ?>; font-weight: 600;">
                                        <?= $m['status'] == 'Live' ? '🔴 LIVE' : htmlspecialchars($m['status']) ?>
                                    </span>
                                </div>
                                <p style="margin: 0 0 8px 0; font-size: 13px; color: var(--text-muted);">
                                    <i class="far fa-calendar-alt"></i> <?= date('M d, Y h:i A', strtotime($m['scheduled_time'])) ?>
                                </p>
                                <p style="margin: 0 0 16px 0; font-size: 13px; color: var(--text-muted);">
                                    <i class="far fa-user"></i> Host: <?= htmlspecialchars($m['host_name'] ?? 'System') ?>
                                </p>
                                <button onclick="joinMeeting('<?= htmlspecialchars($m['room_name']) ?>', '<?= htmlspecialchars($m['title']) ?>')" style="width: 100%; background: var(--primary-color); color: white; border: none; padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                                    Join Meeting
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Jitsi Iframe Container (Hidden by default) -->
            <div id="jitsiContainer" style="display: none; width: 100%; height: 600px; background: #000; position: relative;">
                <div style="position: absolute; top: 16px; left: 16px; z-index: 10;">
                    <button onclick="leaveMeeting()" style="background: rgba(0,0,0,0.6); color: white; border: 1px solid rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Leave
                    </button>
                </div>
                <div id="jitsiIframeRoot" style="width: 100%; height: 100%;"></div>
            </div>

        </div>

        <!-- Sidebar Panel -->
        <div style="background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-card); padding: 20px;">
            <h3 style="margin: 0 0 16px 0; font-size: 16px; color: var(--text-heading);">Quick Join</h3>
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">Have a room code? Enter it below to join instantly.</p>
            <input type="text" id="quickRoomName" placeholder="Room Name (e.g., DesignSync)" style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main); margin-bottom: 12px;">
            <button onclick="joinMeeting(document.getElementById('quickRoomName').value, 'Ad-hoc Meeting')" style="width: 100%; background: var(--bg-hover); color: var(--text-heading); border: 1px solid var(--border-card); padding: 10px; border-radius: 6px; font-weight: 600; cursor: pointer;">
                Join Instantly
            </button>
        </div>
    </div>
</div>

<!-- Meeting Creation Modal -->
<div id="meetingModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-card); width: 450px; border-radius: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); border: 1px solid var(--border-card); overflow: hidden; transform: scale(0.95); transition: transform 0.2s;" id="meetingModalContent">
        <div style="padding: 24px; border-bottom: 1px solid var(--border-card); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; color: var(--text-heading); font-size: 18px;">Schedule Meeting</h3>
            <button onclick="closeMeetingModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 20px;">&times;</button>
        </div>
        <div style="padding: 24px;">
            <form id="createMeetingForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-heading);">Meeting Title</label>
                    <input type="text" name="title" required placeholder="e.g., Weekly Sync" style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main);">
                </div>
                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-heading);">Scheduled Time</label>
                    <input type="datetime-local" name="scheduled_time" required style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main);">
                </div>
                <div style="margin-bottom: 24px;">
                    <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--text-heading);">Room Name Identifier (Optional)</label>
                    <input type="text" name="room_name" placeholder="Leave blank to auto-generate" style="width: 100%; padding: 10px; border: 1px solid var(--border-card); border-radius: 6px; background: var(--bg-main); color: var(--text-main);">
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeMeetingModal()" style="padding: 10px 16px; background: transparent; border: 1px solid var(--border-card); border-radius: 6px; color: var(--text-main); font-weight: 600; cursor: pointer;">Cancel</button>
                    <button type="submit" style="padding: 10px 16px; background: var(--primary-color); border: none; border-radius: 6px; color: white; font-weight: 600; cursor: pointer;">Schedule Meeting</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://meet.jit.si/external_api.js"></script>
<script>
    let jitsiApi = null;
    const currentUser = <?= json_encode($_SESSION['full_name'] ?? 'User') ?>;

    function openMeetingModal() {
        const modal = document.getElementById('meetingModal');
        modal.style.display = 'flex';
        setTimeout(() => document.getElementById('meetingModalContent').style.transform = 'scale(1)', 10);
    }

    function closeMeetingModal() {
        document.getElementById('meetingModalContent').style.transform = 'scale(0.95)';
        setTimeout(() => document.getElementById('meetingModal').style.display = 'none', 200);
    }

    document.getElementById('createMeetingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('controllers/meeting_api.php?action=create', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error creating meeting: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error(err);
            alert('Failed to communicate with server.');
        });
    });

    function joinMeeting(roomName, subject) {
        if (!roomName) return alert('Room name is required.');
        
        document.getElementById('meetingListContainer').style.display = 'none';
        document.getElementById('jitsiContainer').style.display = 'block';

        const domain = 'meet.jit.si';
        const options = {
            roomName: 'cms_' + roomName.replace(/[^a-zA-Z0-9]/g, ''),
            width: '100%',
            height: '100%',
            parentNode: document.querySelector('#jitsiIframeRoot'),
            userInfo: {
                displayName: currentUser
            },
            configOverwrite: {
                startWithAudioMuted: true,
                startWithVideoMuted: true
            },
            interfaceConfigOverwrite: {
                SHOW_JITSI_WATERMARK: false
            }
        };
        
        jitsiApi = new JitsiMeetExternalAPI(domain, options);
        jitsiApi.executeCommand('subject', subject);
        
        // Notify server that meeting is live (optional enhancement)
    }

    function leaveMeeting() {
        if (jitsiApi) {
            jitsiApi.dispose();
            jitsiApi = null;
        }
        document.getElementById('jitsiContainer').style.display = 'none';
        document.getElementById('meetingListContainer').style.display = 'block';
    }
</script>

<?php require_once 'includes/footer.php'; ?>
