<?php
require_once 'includes/db.php';
require_once 'includes/header.php';
require_once 'includes/sidebar.php';
requirePermission($pdo, 'access_rooms');

// Auto-migrate schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        name TEXT NOT NULL,
        capacity INTEGER NOT NULL
    )");
    
    // Insert default rooms if empty
    $count = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    if ($count == 0) {
        $pdo->exec("INSERT INTO rooms (name, capacity) VALUES ('Boardroom A', 12), ('Conference Room B', 8), ('Huddle Space 1', 4)");
    }
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS room_bookings (
        id INTEGER PRIMARY KEY AUTO_INCREMENT,
        room_id INTEGER NOT NULL,
        user_id TEXT NOT NULL,
        title TEXT NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$rooms = $pdo->query("SELECT * FROM rooms")->fetchAll(PDO::FETCH_ASSOC);

// Fetch today's bookings
$today = date('Y-m-d');
try {
    $bookings = $pdo->query("SELECT b.*, r.name as room_name, u.name as user_name FROM room_bookings b JOIN rooms r ON b.room_id = r.id LEFT JOIN users u ON (b.user_id = u.login_id OR b.booked_by = u.login_id) WHERE date(b.start_time) = '$today' ORDER BY b.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    try {
        $bookings = $pdo->query("SELECT b.*, r.name as room_name, u.name as user_name FROM room_bookings b JOIN rooms r ON b.room_id = r.id LEFT JOIN users u ON b.user_id = u.login_id WHERE date(b.start_time) = '$today' ORDER BY b.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e2) {
        $bookings = $pdo->query("SELECT b.*, r.name as room_name, u.name as user_name FROM room_bookings b JOIN rooms r ON b.room_id = r.id LEFT JOIN users u ON b.booked_by = u.login_id WHERE date(b.start_time) = '$today' ORDER BY b.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<div class="content-section active">
    <div class="section-header">
        <h2>📅 Room Booking</h2>
        <div>
            <?php if(hasPermission($pdo, 'manage_settings') || in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
            <button class="add-button" style="background:#f59e0b; margin-right:10px;" onclick="document.getElementById('addRoomModal').style.display='flex'">⚙️ Manage Rooms</button>
            <?php endif; ?>
            <button class="add-button" onclick="document.getElementById('bookingModal').style.display='flex'">+ Book a Room</button>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:300px 1fr; gap:20px;">
        
        <!-- Available Rooms -->
        <div>
            <h3 style="color:var(--text-heading); margin-top:0;">Available Rooms</h3>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <?php foreach($rooms as $r): ?>
                <div style="background:white; border-radius:8px; border:1px solid #e2e8f0; padding:15px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div>
                        <div style="font-weight:bold; color:#1e293b;"><?= htmlspecialchars($r['name']) ?></div>
                        <div style="font-size:12px; color:#64748b;">Capacity: <?= $r['capacity'] ?> people</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <?php if(hasPermission($pdo, 'manage_settings') || in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
                        <button onclick='editRoom(<?= json_encode($r) ?>)' style="background:none; border:none; cursor:pointer; color:#3b82f6; font-size:14px;">Edit</button>
                        <form method="POST" action="controllers/save_room_booking.php" onsubmit="return confirm('Delete this room and all its bookings?')" style="margin:0;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="delete_room">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:14px;">Delete</button>
                        </form>
                        <?php endif; ?>
                        <div style="font-size:20px; margin-left:10px;">🚪</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div>
            <h3 style="color:var(--text-heading); margin-top:0;">Today's Schedule (<?= date('M j, Y') ?>)</h3>
            <div style="background:white; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                <?php if(empty($bookings)): ?>
                    <p style="color:#64748b; text-align:center; padding:20px;">No rooms booked for today.</p>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap:15px;">
                        <?php foreach($bookings as $b): 
                            $start = date('g:i A', strtotime($b['start_time']));
                            $end = date('g:i A', strtotime($b['end_time']));
                        ?>
                        <div style="display:flex; gap:15px; align-items:flex-start; border-bottom:1px solid #f1f5f9; padding-bottom:15px;">
                            <div style="width:120px; text-align:right;">
                                <div style="font-weight:bold; color:#1e293b; font-size:14px;"><?= $start ?></div>
                                <div style="font-size:11px; color:#64748b;">to <?= $end ?></div>
                            </div>
                            <div style="width:2px; background:#4f46e5; border-radius:2px; min-height:40px;"></div>
                            <div style="flex:1;">
                                <div style="font-weight:bold; color:#1e293b; font-size:15px;"><?= htmlspecialchars($b['title']) ?></div>
                                <div style="font-size:13px; color:#475569; display:flex; gap:10px; margin-top:4px;">
                                    <span>🚪 <?= htmlspecialchars($b['room_name']) ?></span>
                                    <span>👤 <?= htmlspecialchars($b['user_name']) ?></span>
                                </div>
                            </div>
                            <?php if($b['user_id'] === $_SESSION['login_id'] || in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
                            <form method="POST" action="controllers/save_room_booking.php" onsubmit="return confirm('Cancel this booking?')">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                <button type="submit" style="background:#fee2e2; color:#ef4444; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">Cancel</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- Booking Modal -->
<div class="modal" id="bookingModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Book a Room</h2>
        <form method="POST" action="controllers/save_room_booking.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="book">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Meeting Title</label>
            <input type="text" name="title" required placeholder="e.g. Q3 Planning Sync" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Select Room</label>
            <select name="room_id" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
                <?php foreach($rooms as $r): ?>
                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (Max <?= $r['capacity'] ?>)</option>
                <?php endforeach; ?>
            </select>
            
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:20px;">
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Start Time</label>
                    <input type="datetime-local" name="start_time" required value="<?= date('Y-m-d\TH:00') ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">End Time</label>
                    <input type="datetime-local" name="end_time" required value="<?= date('Y-m-d\TH:00', strtotime('+1 hour')) ?>" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px;">
                </div>
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('bookingModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Confirm Booking</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Room Modal -->
<?php if(hasPermission($pdo, 'manage_settings') || in_array($_SESSION['role'], ['Admin', 'Super Admin'])): ?>
<div class="modal" id="addRoomModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Add New Room</h2>
        <form method="POST" action="controllers/save_room_booking.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="add_room">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Room Name</label>
            <input type="text" name="name" required placeholder="e.g. Executive Boardroom" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Capacity (People)</label>
            <input type="number" name="capacity" required min="1" value="4" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('addRoomModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Save Room</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal" id="editRoomModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:1000;">
    <div class="modal-content" style="background:white; padding:30px; border-radius:12px; width:450px; box-shadow:0 10px 25px rgba(0,0,0,0.1);">
        <h2 style="margin-top:0;">Edit Room</h2>
        <form method="POST" action="controllers/save_room_booking.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="action" value="edit_room">
            <input type="hidden" name="id" id="edit_room_id">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Room Name</label>
            <input type="text" name="name" id="edit_room_name" required style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:15px;">
            
            <label style="display:block; margin-bottom:5px; font-weight:bold; font-size:14px;">Capacity (People)</label>
            <input type="number" name="capacity" id="edit_room_capacity" required min="1" style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; margin-bottom:20px;">
            
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" onclick="document.getElementById('editRoomModal').style.display='none'" style="background:#e2e8f0; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:bold;">Cancel</button>
                <button type="submit" style="background:#4f46e5; color:white; border:none; padding:10px 20px; border-radius:6px; font-weight:bold; cursor:pointer;">Update Room</button>
            </div>
        </form>
    </div>
</div>
<script>
function editRoom(room) {
    document.getElementById('edit_room_id').value = room.id;
    document.getElementById('edit_room_name').value = room.name;
    document.getElementById('edit_room_capacity').value = room.capacity;
    document.getElementById('editRoomModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
