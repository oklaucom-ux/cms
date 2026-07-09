<?php
session_start();
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Auto-migrate schema
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            name TEXT NOT NULL,
            capacity INTEGER NOT NULL
        )");
        
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

    if ($action === 'book') {
        $room_id = intval($_POST['room_id']);
        $title = $_POST['title'];
        $start = $_POST['start_time'];
        $end = $_POST['end_time'];
        
        // Basic overlap check
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_bookings WHERE room_id = ? AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))");
        $stmt->execute([$room_id, $end, $start, $end, $start]);
        if ($stmt->fetchColumn() > 0) {
            die("<script>alert('Room is already booked during this time.'); window.location.href='../room_booking.php';</script>");
        }
        
        $stmt = $pdo->prepare("INSERT INTO room_bookings (room_id, user_id, title, start_time, end_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$room_id, $_SESSION['login_id'], $title, $start, $end]);
        
        header("Location: ../room_booking.php?msg=RoomBooked");
        exit;
    }
    
    if ($action === 'cancel') {
        $id = intval($_POST['id']);
        // Verify ownership or admin
        if (!in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            $stmt = $pdo->prepare("DELETE FROM room_bookings WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['login_id']]);
        } else {
            $pdo->prepare("DELETE FROM room_bookings WHERE id = ?")->execute([$id]);
        }
        
        header("Location: ../room_booking.php?msg=BookingCancelled");
        exit;
    }
    
    // --- ADMIN ROOM MANAGEMENT ---
    if (in_array($action, ['add_room', 'edit_room', 'delete_room'])) {
        if (!hasPermission($pdo, 'manage_settings') && !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
            die("Unauthorized to manage rooms.");
        }
        
        if ($action === 'add_room') {
            $name = trim($_POST['name']);
            $capacity = intval($_POST['capacity']);
            $stmt = $pdo->prepare("INSERT INTO rooms (name, capacity) VALUES (?, ?)");
            $stmt->execute([$name, $capacity]);
        } elseif ($action === 'edit_room') {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $capacity = intval($_POST['capacity']);
            $stmt = $pdo->prepare("UPDATE rooms SET name = ?, capacity = ? WHERE id = ?");
            $stmt->execute([$name, $capacity, $id]);
        } elseif ($action === 'delete_room') {
            $id = intval($_POST['id']);
            $pdo->prepare("DELETE FROM rooms WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM room_bookings WHERE room_id = ?")->execute([$id]);
        }
        
        header("Location: ../room_booking.php?msg=RoomsUpdated");
        exit;
    }
}
?>
