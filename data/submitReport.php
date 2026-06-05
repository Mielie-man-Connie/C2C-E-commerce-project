<?php
/* Submit Report: save user report (JSON response) */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$category = trim($_POST['category'] ?? '');
$subject  = trim($_POST['subject']  ?? '');
$details  = trim($_POST['details']  ?? '');

if (!$category || !$subject || !$details) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit;
}

require_once __DIR__ . '/../data/database.php';

try {
    /* Create table if it doesn't exist yet */
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS reports (
            reportID   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            userID     INT DEFAULT NULL,
            category   VARCHAR(50)  NOT NULL,
            subject    VARCHAR(120) NOT NULL,
            details    TEXT         NOT NULL,
            status     VARCHAR(20)  NOT NULL DEFAULT "open",
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $stmt = $pdo->prepare(
        'INSERT INTO reports (userID, category, subject, details)
         VALUES (:uid, :cat, :sub, :det)'
    );
    $stmt->execute([
        ':uid' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        ':cat' => $category,
        ':sub' => $subject,
        ':det' => $details,
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log('submitReport error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'DB error']);
}