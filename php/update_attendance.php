<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/university-system/php/db_connect.php';

// Set the default timezone to match your location
date_default_timezone_set('Asia/Kolkata'); // Change to your appropriate timezone

function updateAttendance($user_id, $action) {
    global $pdo;
    
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Check if today's record already exists
    $stmt = $pdo->prepare("SELECT * FROM staff_attendance WHERE user_id = ? AND date = ?");
    $stmt->execute([$user_id, $current_date]);
    $record = $stmt->fetch();

    if ($action === 'login') {
        if ($record) {
            // Update check-in if not already set
            if (empty($record['check_in'])) {
                $update = $pdo->prepare("UPDATE staff_attendance SET check_in = ?, status = 'present', updated_at = NOW() WHERE id = ?");
                $update->execute([$current_time, $record['id']]);
            }
        } else {
            // Create new record
            $insert = $pdo->prepare("INSERT INTO staff_attendance (user_id, date, check_in, status, created_at, updated_at) VALUES (?, ?, ?, 'present', NOW(), NOW())");
            $insert->execute([$user_id, $current_date, $current_time]);
        }
        
        // Check if late (after 9:30 AM)
        if (date('H:i') > '09:30') {
            $update = $pdo->prepare("UPDATE staff_attendance SET status = 'late' WHERE user_id = ? AND date = ?");
            $update->execute([$user_id, $current_date]);
        }
    } elseif ($action === 'logout' && $record) {
        // Update check-out time
        $update = $pdo->prepare("UPDATE staff_attendance SET check_out = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$current_time, $record['id']]);
        
        // Calculate hours worked and update notes if less than 8 hours
        if ($record['check_in']) {
            $check_in = new DateTime($record['check_in'], new DateTimeZone('Asia/Kolkata'));
            $check_out = new DateTime($current_time, new DateTimeZone('Asia/Kolkata'));
            $diff = $check_out->diff($check_in);
            $hours_worked = $diff->h + ($diff->i / 60);
            
            if ($hours_worked < 8) {
                $notes = "Worked only " . round($hours_worked, 2) . " hours";
                $update = $pdo->prepare("UPDATE staff_attendance SET notes = ? WHERE id = ?");
                $update->execute([$notes, $record['id']]);
            }
        }
    }
}
?>