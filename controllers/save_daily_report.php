<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['login_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }
    header("Location: ../login.php");
    exit();
}

// Ensure schema exists
require_once '../migrations/016_create_daily_reports_schema.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['login_id'];
    $reportId = !empty($_POST['id']) ? intval($_POST['id']) : null;
    $reportDate = !empty($_POST['report_date']) ? trim($_POST['report_date']) : date('Y-m-d');
    $tasksCompleted = trim($_POST['tasks_completed'] ?? '');
    $wip = trim($_POST['work_in_progress'] ?? '');
    $blockers = trim($_POST['blockers'] ?? '');
    $planTomorrow = trim($_POST['plan_for_tomorrow'] ?? '');
    $hoursWorked = isset($_POST['hours_worked']) ? floatval($_POST['hours_worked']) : 8.00;

    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || 
              (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if (empty($tasksCompleted) && empty($wip)) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Please provide details for tasks completed or work in progress.']);
            exit();
        } else {
            $_SESSION['flash_error'] = 'Please provide details for tasks completed or work in progress.';
            header("Location: ../daily_reports.php");
            exit();
        }
    }

    try {
        if ($reportId) {
            // Check ownership unless admin/manager
            $checkStmt = $pdo->prepare("SELECT user_id FROM daily_reports WHERE id = ?");
            $checkStmt->execute([$reportId]);
            $owner = $checkStmt->fetchColumn();

            $isAdmin = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'System Admin']);
            if ($owner !== $userId && !$isAdmin) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized to edit this report.']);
                    exit();
                } else {
                    die("Unauthorized");
                }
            }

            $stmt = $pdo->prepare("UPDATE daily_reports SET report_date = ?, tasks_completed = ?, work_in_progress = ?, blockers = ?, plan_for_tomorrow = ?, hours_worked = ?, status = 'Submitted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$reportDate, $tasksCompleted, $wip, $blockers, $planTomorrow, $hoursWorked, $reportId]);

            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$userId, 'Update Daily Report', "Updated report ID {$reportId} for {$reportDate}"]);
        } else {
            // Check if user already submitted a report for this date
            $dupStmt = $pdo->prepare("SELECT id FROM daily_reports WHERE user_id = ? AND report_date = ?");
            $dupStmt->execute([$userId, $reportDate]);
            $existingId = $dupStmt->fetchColumn();

            if ($existingId) {
                $stmt = $pdo->prepare("UPDATE daily_reports SET tasks_completed = ?, work_in_progress = ?, blockers = ?, plan_for_tomorrow = ?, hours_worked = ?, status = 'Submitted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$tasksCompleted, $wip, $blockers, $planTomorrow, $hoursWorked, $existingId]);
                $reportId = $existingId;
            } else {
                $stmt = $pdo->prepare("INSERT INTO daily_reports (user_id, report_date, tasks_completed, work_in_progress, blockers, plan_for_tomorrow, hours_worked, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Submitted')");
                $stmt->execute([$userId, $reportDate, $tasksCompleted, $wip, $blockers, $planTomorrow, $hoursWorked]);
                $reportId = $pdo->lastInsertId();
            }

            $pdo->prepare("INSERT INTO audit_trail (user_id, action, details) VALUES (?, ?, ?)")->execute([$userId, 'Submit Daily Report', "Submitted daily report for {$reportDate}"]);
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Daily Work Report submitted successfully.', 'id' => $reportId]);
            exit();
        } else {
            $_SESSION['flash_success'] = 'Daily Work Report submitted successfully.';
            header("Location: ../daily_reports.php");
            exit();
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
            exit();
        } else {
            die("Error saving report: " . $e->getMessage());
        }
    }
}
