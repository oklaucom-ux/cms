<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'view_attendance');

// Auto-migrate attendance table
try {
    $isMysql = (strpos($pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'mysql') !== false);
    $pkDef = $isMysql ? "INT AUTO_INCREMENT PRIMARY KEY" : "INTEGER PRIMARY KEY";

    $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
        id {$pkDef},
        user_id VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        clock_in DATETIME,
        clock_out DATETIME,
        status VARCHAR(50) DEFAULT 'Present',
        ip_address VARCHAR(45) DEFAULT NULL,
        latitude VARCHAR(50) DEFAULT NULL,
        longitude VARCHAR(50) DEFAULT NULL
    )");
} catch (Exception $e) {}

// Add columns if they don't exist (for existing tables)
try { $pdo->exec("ALTER TABLE attendance ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE attendance ADD COLUMN latitude VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE attendance ADD COLUMN longitude VARCHAR(50) DEFAULT NULL"); } catch(Exception $e) {}

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
    <div class="glass-card" style="padding:40px; border-radius:24px; text-align:center; margin-bottom:40px; max-width:560px; margin-left:auto; margin-right:auto; position:relative; overflow:hidden;">
        <!-- Glowing background effect -->
        <div style="position:absolute; top:-50%; left:-50%; width:200%; height:200%; background:radial-gradient(circle at center, rgba(99,102,241,0.05) 0%, transparent 60%); pointer-events:none; z-index:0;"></div>
        
        <h3 style="font-size: 22px; color: var(--text-heading); margin-bottom: 28px; position:relative; z-index:1;">Today: <?= date('l, F jS Y') ?></h3>
        
        <div style="background: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.9); padding: 24px 40px; border-radius: 20px; font-size: 64px; font-family: monospace; font-weight: 800; background: linear-gradient(135deg, var(--primary-color), #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent; margin-bottom: 36px; letter-spacing: 4px; display:inline-block; box-shadow: 0 10px 30px rgba(0,0,0,0.03), inset 0 2px 4px rgba(255,255,255,1); position:relative; z-index:1;" id="liveClock">
            00:00:00
        </div>

        <form method="POST" action="controllers/attendance_api.php" id="attendanceForm" style="position:relative; z-index:1;">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="latitude" id="latField">
            <input type="hidden" name="longitude" id="lngField">
            <?php if($activeLeave): ?>
                <div style="background:rgba(254, 252, 232, 0.9); color:#a16207; border: 1px solid #fef08a; padding:20px 32px; font-size:18px; border-radius:16px; font-weight:700; margin-bottom: 20px; backdrop-filter:blur(4px); box-shadow:0 4px 12px rgba(253,224,71,0.2);">
                    🌴 You are currently on Approved Leave (<?= htmlspecialchars($activeLeave['leave_type']) ?>)
                </div>
                <!-- Block Clocking in entirely -->
            <?php elseif(!$clockedIn): ?>
                <!-- Can Clock In -->
                <input type="hidden" name="action" value="clock_in">
                <button type="submit" style="background:linear-gradient(135deg, #10b981, #059669); color:white; border:none; padding:18px 48px; font-size:18px; border-radius:99px; cursor:pointer; font-weight:700; width: 100%; transition: all 0.2s; box-shadow:0 8px 25px rgba(16,185,129,0.35);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 30px rgba(16,185,129,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(16,185,129,0.35)';">
                    🕐 Clock In Now
                </button>
            <?php elseif($clockedIn && !$clockedOut): ?>
                <!-- Can Clock Out -->
                <div style="color: #10b981; margin-bottom: 20px; font-weight: 700; font-size:15px; background:rgba(16,185,129,0.1); display:inline-block; padding:8px 16px; border-radius:99px;">Clocked in at: <?= date('h:i A', strtotime($myAttendance['clock_in'])) ?></div>
                <input type="hidden" name="action" value="clock_out">
                <button type="submit" style="background:linear-gradient(135deg, #ef4444, #dc2626); color:white; border:none; padding:18px 48px; font-size:18px; border-radius:99px; cursor:pointer; font-weight:700; width: 100%; transition: all 0.2s; box-shadow:0 8px 25px rgba(239,68,68,0.35);" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 12px 30px rgba(239,68,68,0.45)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 8px 25px rgba(239,68,68,0.35)';">
                    🛑 Clock Out
                </button>
            <?php else: ?>
                <!-- Done for the day -->
                <div style="display:flex; justify-content:center; gap:16px; margin-bottom: 24px;">
                    <div style="color: #10b981; font-weight: 700; font-size:14px; background:rgba(16,185,129,0.1); padding:8px 16px; border-radius:99px;">In: <?= date('h:i A', strtotime($myAttendance['clock_in'])) ?></div>
                    <div style="color: #ef4444; font-weight: 700; font-size:14px; background:rgba(239,68,68,0.1); padding:8px 16px; border-radius:99px;">Out: <?= date('h:i A', strtotime($myAttendance['clock_out'])) ?></div>
                </div>
                <div style="background:linear-gradient(135deg, #f3f4f6, #e5e7eb); color:#4b5563; padding:18px 48px; font-size:18px; border-radius:99px; font-weight:700; border:1px solid rgba(255,255,255,0.5); box-shadow:0 4px 10px rgba(0,0,0,0.05);">
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
                    <th>Location</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><?= $h['date'] ?></td>
                    <td><?= htmlspecialchars($h['login_id']) ?></td>
                    <td><?= $h['clock_in'] ? date('h:i A', strtotime($h['clock_in'])) : '-' ?></td>
                    <td><?= $h['clock_out'] ? date('h:i A', strtotime($h['clock_out'])) : '-' ?></td>
                    <td style="font-size:12px; color:#6b7280;"><?= $h['lat'] ? htmlspecialchars(substr($h['lat'],0,7).','.substr($h['lng'],0,7)) : 'N/A' ?></td>
                    <td>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $h['status'])) ?>">
                            <?= htmlspecialchars($h['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($history)): ?>
                <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
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

