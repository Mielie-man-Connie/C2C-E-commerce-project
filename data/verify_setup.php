<?php
require_once __DIR__ . '/database.php';

echo "DATABASE VERIFICATION\n\n";

// Check if isAdmin column exists and get its value for user 1
try {
    $s = $pdo->prepare('SELECT userID, username, isAdmin FROM accounts WHERE userID=1');
    $s->execute();
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "✓ User 1 ('{$row['username']}'): isAdmin = " . ($row['isAdmin'] ?? 'NULL') . "\n";
    } else {
        echo "⚠ User 1 not found\n";
    }
} catch (PDOException $e) {
    echo "✗ Error checking user 1: " . $e->getMessage() . "\n";
}

// Check if response column exists in reports
try {
    $s = $pdo->query('SELECT response FROM reports LIMIT 1');
    echo "✓ Reports table has 'response' column\n";
} catch (PDOException $e) {
    echo "✗ Reports table missing 'response' column\n";
}

// Check if imgData column exists in images
try {
    $s = $pdo->query('SELECT imgData FROM images LIMIT 1');
    echo "✓ Images table has 'imgData' column\n";
} catch (PDOException $e) {
    echo "✗ Images table missing 'imgData' column\n";
}

// Verify tables have correct columns
$tables_to_check = [
    'accounts' => ['isAdmin', 'profileDescription'],
    'images' => ['imgData'],
    'reports' => ['response'],
];

foreach ($tables_to_check as $table => $cols) {
    $dbCols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($cols as $col) {
        if (in_array($col, $dbCols)) {
            echo "✓ $table.$col exists\n";
        } else {
            echo "✗ $table.$col missing\n";
        }
    }
}

echo "\n✓ All verifications complete!\n";
?>
