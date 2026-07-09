<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Auto-migrate attendance table
$pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    user_id TEXT NOT NULL,
    date TEXT NOT NULL,
    clock_in DATETIME,
    clock_out DATETIME,
    status TEXT DEFAULT 'Present'
)");

$me = $_SESSION['login_id'];
$today = date('Y-m-d');
$isAdmin = hasPermission($pdo, 'view_attendance') || hasPermission($pdo, 'manage_attendance');

// Check today's status
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$me, $today]);
$myAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

$clockedIn = $myAttendance && !empty($myAttendance['clock_in']);
$clockedOut = $myAttendance && !empty($myAttendance['clock_out']);

// Enhancement: Detect Active Approved Leaves
$stmtLeave = $pdo->prepare("SELECT * FROM leaves WHERE user_id = ? AND status = 'Approved' AND ? BETWEEN start_date AND end_date");
$stmtLeave->execute([$me, $today]);
$activeLeave = $stmtLeave->fetch(PDO::FETCH_ASSOC);
?>

<div class="content-section active">
    <div class="section-header">
        <h2>Time & Attendance Tracker</h2>
        <button class="view-button" onclick="window.location.href='controllers/export_csv.php?table=attendance'">📥 Export Data</button>
    </div>

    <!-- Clock Widget -->
    <div style="background:var(--bg-card); padding:32px; border-radius:var(--radius-lg); border:1px solid var(--border-card); box-shadow:var(--shadow-sm); text-align:center; margin-bottom:32px; max-width:560px; margin-left:auto; margin-right:auto; ">
        <h3 style="font-size: 20px; color: #4b5563; margin-bottom: 24px;">Today: <?= date('l, F jS Y') ?></h3>
        
        <div style="background: var(--bg-header); border: 1px solid var(--border-card); padding: 20px; border-radius: 12px; font-size: 56px; font-family: monospace; font-weight: 800; color: var(--primary-color); margin-bottom: 32px; letter-spacing: 2px; display:inline-block; box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);" id="liveClock">
            00:00:00
        </div>

        <form method="POST" action="controllers/attendance_api.php" id="attendanceForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="latitude" id="latField">
            <input type="hidden" name="longitude" id="lngField">
            <?php if($activeLeave): ?>
                <div style="background:#fefce8; color:#a16207; border: 1px solid #fef08a; padding:16px 32px; font-size:18px; border-radius:12px; font-weight:bold; margin-bottom: 20px;">
                    🌴 You are currently on Approved Leave (<?= htmlspecialchars($activeLeave['leave_type']) ?>)
                </div>
                <!-- Block Clocking in entirely -->
            <?php elseif(!$clockedIn): ?>
                <!-- Can Clock In -->
                <input type="hidden" name="action" value="clock_in">
                <button type="submit" style="background:#10b981; color:white; border:none; padding:16px 48px; font-size:18px; border-radius:40px; cursor:pointer; font-weight:bold; width: 100%; transition: transform 0.2s;">
                    🕐 Clock In Now
                </button>
            <?php elseif($clockedIn && !$clockedOut): ?>
                <!-- Can Clock Out -->
                <div style="color: #10b981; margin-bottom: 16px; font-weight: bold;">Clocked in at: <?= date('h:i A', strtotime($myAttendance['clock_in'])) ?></div>
                <input type="hidden" name="action" value="clock_out">
                <button type="submit" style="background:#ef4444; color:white; border:none; padding:16px 48px; font-size:18px; border-radius:40px; cursor:pointer; font-weight:bold; width: 100%; transition: transform 0.2s;">
                    🛑 Clock Out
                </button>
            <?php else: ?>
                <!-- Done for the day -->
                <div style="color: #10b981; margin-bottom: 8px; font-weight: bold;">Clocked in at: <?= date('h:i A', strtotime($myAttendance['clock_in'])) ?></div>
                <div style="color: #ef4444; margin-bottom: 16px; font-weight: bold;">Clocked out at: <?= date('h:i A', strtotime($myAttendance['clock_out'])) ?></div>
                <div style="background:#f3f4f6; color:#6b7280; padding:16px 48px; font-size:18px; border-radius:40px; font-weight:bold;">
                    ✅ Shift Completed
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Attendance Grid -->
    <div class="data-table">
        <h3 style="padding: 20px 24px; border-bottom: 1px solid #f3f4f6; margin: 0;"><?= $isAdmin ? "Company-Wide Timeline" : "My Timeline" ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>User ID</th>
                    <th>Clock In</th>
                    <th>Clock Out</th>
                    <th>Location Info</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $query = $isAdmin ? "SELECT * FROM attendance ORDER BY date DESC, clock_in DESC" : "SELECT * FROM attendance WHERE user_id = '$me' ORDER BY date DESC";
                foreach($pdo->query($query) as $row): 
                    $inTime = $row['clock_in'] ? date('h:i A', strtotime($row['clock_in'])) : '-';
                    $outTime = $row['clock_out'] ? date('h:i A', strtotime($row['clock_out'])) : '-';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($row['date']) ?></strong></td>
                    <td><?= htmlspecialchars($row['user_id']) ?></td>
                    <td style="color:#10b981; font-weight:500;"><?= $inTime ?></td>
                    <td style="color:#ef4444; font-weight:500;"><?= $outTime ?></td>
                    <td style="font-size:12px;color:var(--text-muted);">
                        <?php if(!empty($row['ip_address'])): ?>
                            IP: <?= htmlspecialchars($row['ip_address']) ?><br>
                            <?php if(!empty($row['latitude'])): ?>
                                Geo: <?= htmlspecialchars(substr($row['latitude'],0,7)) ?>, <?= htmlspecialchars(substr($row['longitude'],0,7)) ?>
                            <?php endif; ?>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                    <td><span style="background: <?= $row['status']=='Present' ? '#dcfce7' : '#fef2f2' ?>; color: <?= $row['status']=='Present' ? '#16a34a' : '#dc2626' ?>; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight:600;"><?= htmlspecialchars($row['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
setInterval(() => {
    document.getElementById('liveClock').textContent = new Date().toLocaleTimeString('en-US', { hour12: false });
}, 1000);

const form = document.getElementById('attendanceForm');
if(form) {
    form.addEventListener('submit', function(e) {
        if (!document.getElementById('latField').value) {
            e.preventDefault();
            // Try to get geolocation first
            if (navigator.geolocation) {
                if(typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Getting Location...',
                        html: 'Please wait, checking coordinates.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });
                }
                navigator.geolocation.getCurrentPosition(pos => {
                    document.getElementById('latField').value = pos.coords.latitude;
                    document.getElementById('lngField').value = pos.coords.longitude;
                    if(typeof Swal !== 'undefined') Swal.close();
                    form.submit();
                }, err => {
                    if(typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Location Failed',
                            text: 'Geolocation blocked or timed out. Proceeding without it...',
                            showConfirmButton: false,
                            timer: 2000
                        });
                    }
                    document.getElementById('latField').value = 'blocked'; 
                    setTimeout(()=>form.submit(), 2000);
                }, { timeout: 5000, maximumAge: 0 });
            } else {
                document.getElementById('latField').value = 'unsupported';
                form.submit();
            }
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
