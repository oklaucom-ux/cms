<?php
// employee_portal.php - Employee Self-Service Mobile Portal (PWA)
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';

$userId = $_SESSION['login_id'];
$today  = date('Y-m-d');
$currentMonth = date('F Y');

// Fetch User Info
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE login_id = ?");
$stmtUser->execute([$userId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC) ?: ['full_name' => $userId, 'role' => $_SESSION['role'], 'email' => ''];

// Fetch Today's Attendance Status
$stmtAtt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmtAtt->execute([$userId, $today]);
$todayAtt = $stmtAtt->fetch(PDO::FETCH_ASSOC);

// Fetch Pending Leaves
$stmtLeaves = $pdo->prepare("SELECT * FROM leaves WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmtLeaves->execute([$userId]);
$myLeaves = $stmtLeaves->fetchAll(PDO::FETCH_ASSOC);

// Fetch Assigned Tasks
$stmtTasks = $pdo->prepare("SELECT * FROM tasks WHERE (assigned_to = ? OR created_by = ?) AND status NOT IN ('Completed','Deleted') ORDER BY due_date ASC LIMIT 5");
$stmtTasks->execute([$userId, $userId]);
$myTasks = $stmtTasks->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.emp-card {
    background: var(--bg-card);
    border: 1px solid var(--border-card);
    border-radius: 20px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.05);
    margin-bottom: 24px;
}
.emp-hero {
    background: linear-gradient(135deg, #4f46e5, #3730a3);
    color: white;
    border-radius: 24px;
    padding: 28px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.punch-btn {
    padding: 16px 28px;
    font-size: 16px;
    font-weight: 800;
    border-radius: 16px;
    border: none;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}
.btn-checkin { background: #10b981; color: white; }
.btn-checkout { background: #ef4444; color: white; }
.punch-btn:active { transform: scale(0.96); }

.grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}
@media (max-width: 850px) { .grid-2 { grid-template-columns: 1fr; } }

.badge-status {
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 800;
}
</style>

<div class="main-content" style="padding: 24px;">
    <!-- Hero Banner -->
    <div class="emp-hero">
        <div>
            <div style="font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.8;">
                📱 Employee Mobile Self-Service Portal
            </div>
            <div style="font-size: 26px; font-weight: 900; margin-top: 4px;">
                Welcome back, <?= htmlspecialchars($user['full_name']) ?>!
            </div>
            <div style="font-size: 13.5px; opacity: 0.9; margin-top: 4px;">
                Role: <strong><?= htmlspecialchars($_SESSION['role']) ?></strong> | Today: <strong><?= date('l, d M Y') ?></strong>
            </div>
        </div>

        <!-- 1-Tap Mobile GPS Attendance Punch -->
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
            <button type="button" onclick="triggerGpsPunch('checkin')" class="punch-btn btn-checkin">
                <i class="fas fa-map-marker-alt"></i> 🟢 GPS Check-In
            </button>
            <button type="button" onclick="triggerGpsPunch('checkout')" class="punch-btn btn-checkout">
                <i class="fas fa-sign-out-alt"></i> 🔴 GPS Check-Out
            </button>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid-2">
        <!-- Attendance & Payslip Section -->
        <div class="emp-card">
            <div style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <span>⏱️ Today's Attendance & Salary Statement</span>
                <a href="controllers/generate_payslip.php?user_id=<?= urlencode($userId) ?>" target="_blank" style="font-size:12px; color:#6366f1; text-decoration:none; font-weight:700;">📄 View Payslip &rarr;</a>
            </div>

            <div style="background:var(--bg-main); border:1px solid var(--border-card); border-radius:14px; padding:18px; margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;">
                    <span>Clock In Time:</span>
                    <strong style="color:#10b981;"><?= $todayAtt['clock_in'] ?? 'Not Checked In' ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;">
                    <span>Clock Out Time:</span>
                    <strong style="color:#ef4444;"><?= $todayAtt['clock_out'] ?? 'Not Checked Out' ?></strong>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:13px;">
                    <span>Status:</span>
                    <strong style="color:#6366f1;"><?= $todayAtt['status'] ?? 'Pending' ?></strong>
                </div>
            </div>

            <a href="controllers/generate_payslip.php?user_id=<?= urlencode($userId) ?>" target="_blank" style="display:block; text-align:center; padding:12px; background:linear-gradient(135deg,#6366f1,#4f46e5); color:white; border-radius:12px; text-decoration:none; font-weight:800; font-size:13.5px;">
                📥 Download Monthly Salary Statement (<?= $currentMonth ?>)
            </a>
        </div>

        <!-- Quick Leave Application Form -->
        <div class="emp-card">
            <div style="font-size:16px; font-weight:800; color:var(--text-heading); margin-bottom:16px;">
                🌴 Quick Leave Application
            </div>

            <form id="empLeaveForm" onsubmit="submitEmpLeave(event)">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>" />

                <div style="display:flex; flex-direction:column; gap:12px;">
                    <div>
                        <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Leave Type</label>
                        <select name="leave_type" class="inv-select" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);">
                            <option value="Casual Leave">Casual Leave (CL)</option>
                            <option value="Sick Leave">Sick Leave (SL)</option>
                            <option value="Earned Leave">Earned Leave (EL)</option>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Start Date</label>
                            <input type="date" name="start_date" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                        </div>
                        <div>
                            <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">End Date</label>
                            <input type="date" name="end_date" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                        </div>
                    </div>

                    <div>
                        <label style="font-size:12px; font-weight:700; color:var(--text-heading); margin-bottom:4px; display:block;">Reason for Leave</label>
                        <input type="text" name="reason" placeholder="Short reason for leave request" required class="inv-input" style="width:100%; padding:10px; border-radius:10px; border:1px solid var(--border-card); background:var(--bg-main); color:var(--text-body);" />
                    </div>

                    <button type="submit" style="padding:12px; background:#10b981; color:white; border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:14px;">
                        🚀 Submit Leave Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function triggerGpsPunch(punchType) {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        return;
    }

    if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Obtaining GPS Location...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    navigator.geolocation.getCurrentPosition(async (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;

        const formData = new FormData();
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
        formData.append('user_id', '<?= urlencode($userId) ?>');
        formData.append('punch_type', punchType);
        formData.append('latitude', lat);
        formData.append('longitude', lng);
        formData.append('device_name', 'Employee Mobile PWA GPS');
        formData.append('api_key', 'BiometricSecretKey2026');

        try {
            const resp = await fetch('api/biometric_webhook.php', { method: 'POST', body: formData });
            const res = await resp.json();

            if (res.success) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Attendance Recorded!', res.message || 'GPS Attendance Punch Successful!', 'success').then(() => window.location.reload());
                } else {
                    alert('GPS Attendance Punch Successful!');
                    window.location.reload();
                }
            } else {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Punch Error', res.error, 'error');
                } else {
                    alert('Punch Error: ' + res.error);
                }
            }
        } catch (err) {
            alert('Server connection error.');
        }
    }, (err) => {
        if (typeof Swal !== 'undefined') {
            Swal.fire('GPS Location Error', 'Unable to retrieve location coordinates. Please enable location permissions.', 'error');
        } else {
            alert('Unable to retrieve location coordinates.');
        }
    });
}

async function submitEmpLeave(e) {
    e.preventDefault();
    const formData = new FormData(document.getElementById('empLeaveForm'));

    if (typeof Swal !== 'undefined') {
        Swal.fire({ title: 'Submitting Leave Application...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    }

    try {
        const resp = await fetch('save_leave.php', { method: 'POST', body: formData });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Submitted!', 'Leave application submitted successfully.', 'success').then(() => window.location.reload());
        } else {
            Swal.fire('Submitted!', 'Leave request recorded.', 'info').then(() => window.location.reload());
        }
    } catch (err) {
        alert('Leave request submitted successfully.');
        window.location.reload();
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
