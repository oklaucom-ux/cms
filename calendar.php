<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js'></script>

<div class="content-section active">
    <div class="section-header">
        <h2>Enterprise Visual Calendar & Scheduler</h2>
        <button class="add-button" onclick="openMeetingModal()">📅 Schedule Meeting</button>
    </div>

    <div style="background: white; padding: 24px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        <div id='calendar'></div>
    </div>
</div>

<style>
/* FullCalendar internal adjustments for our layout */
#calendar { max-width: 100% !important; margin: 0 auto; height: 75vh; }
.fc-event { cursor: pointer; border: none; padding: 3px 6px; border-radius: 4px; font-weight: 600; font-size: 13px; }
.fc-toolbar-title { color: var(--text-heading); font-family: 'Inter', sans-serif; font-weight: 700; }
.fc-button-primary { background-color: #4f46e5 !important; border-color: #4f46e5 !important; }
.fc-button-primary:hover { background-color: #4338ca !important; }
.fc .fc-daygrid-day-number { color: var(--text-body); text-decoration: none; font-weight: 500; font-family: 'Inter', sans-serif; }
.fc .fc-col-header-cell-cushion { color: var(--text-muted); font-family: 'Inter', sans-serif; }
</style>



<!-- Add Meeting Modal -->
<div id="meetingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="document.getElementById('meetingModal').style.display='none'">&times;</span>
        <h2>Schedule New Meeting</h2>
        <form method="POST" action="controllers/save_meeting.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Meeting Subject</label>
                <input type="text" name="title" required placeholder="E.g., Q3 Project Sync">
            </div>
            <div class="form-group" style="display: flex; gap: 10px;">
                <div style="flex: 1;">
                    <label>Start Time</label>
                    <input type="datetime-local" name="start_time" id="meet_start" required>
                </div>
                <div style="flex: 1;">
                    <label>End Time</label>
                    <input type="datetime-local" name="end_time" id="meet_end" required>
                </div>
            </div>
            <div class="form-group">
                <label>Description & Links (Zoom, Google Meet)</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Invite Participants</label>
                <select name="participants[]" multiple required style="height: 120px;">
                    <option value="ALL" style="font-weight:bold; color:#4f46e5;">* Invite ALL Users</option>
                    <?php 
                    $usersList = $pdo->query("SELECT login_id, name FROM users WHERE status='Active'")->fetchAll(PDO::FETCH_ASSOC);
                    foreach($usersList as $u): ?>
                        <option value="<?= htmlspecialchars($u['login_id']) ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['login_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#6b7280; display:block; margin-top:5px;">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="submit">Dispatch Invitations</button>
            </div>
        </form>
    </div>
</div>

<script>
function openMeetingModal(startStr = null, endStr = null) {
    if (startStr && endStr) {
        // Format to step out purely the datetime-local block
        document.getElementById('meet_start').value = startStr.substring(0, 16);
        document.getElementById('meet_end').value = endStr.substring(0, 16);
    }
    document.getElementById('meetingModal').style.display='block';
}

// Ensure the modal hooks accurately after the page triggers
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        select: function(info) {
            // FullCalendar passes ISO strings for startStr and endStr!
            // If they clicked simply a day (not a timeGrid string), append a default time logic if desired
            let s = info.startStr.includes('T') ? info.startStr : info.startStr + 'T09:00:00';
            let e = info.endStr.includes('T') ? info.endStr : info.startStr + 'T10:00:00';
            openMeetingModal(s, e);
        },
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'controllers/calendar_api.php',
        eventClick: function(info) {
            alert(info.event.title + '\nStart: ' + info.event.startStr + (info.event.endStr ? '\nEnd: ' + info.event.endStr : ''));
        }
    });
    calendar.render();
});
</script>

<?php require_once 'includes/footer.php'; ?>
