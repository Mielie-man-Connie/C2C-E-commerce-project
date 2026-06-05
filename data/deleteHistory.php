<?php
/* Delete History: remove a history entry and related images
   POST: itemID
   Verifies ownership, deletes images from images table,
   then deletes the item row. Returns JSON. */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid method']);
    exit;
}

$itemId = filter_input(INPUT_POST, 'itemID', FILTER_VALIDATE_INT);
if (!$itemId) {
    echo json_encode(['success' => false, 'error' => 'Invalid item ID']);
    exit;
}

require_once __DIR__ . '/database.php';
$userId = (int) $_SESSION['user_id'];

try {
    /* Verify ownership and get imageID JSON */
    $stmt = $pdo->prepare('SELECT imageID FROM items WHERE itemID = :id AND userID = :uid LIMIT 1');
    $stmt->execute([':id' => $itemId, ':uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'error' => 'Item not found or access denied']);
        exit;
    }

    /* Delete associated images */
    $imageIds = json_decode($row['imageID'] ?? '[]', true);
    if (is_array($imageIds) && !empty($imageIds)) {
        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $pdo->prepare("DELETE FROM images WHERE imageID IN ($placeholders)")
            ->execute($imageIds);
    }

    /* Delete the item */
    $pdo->prepare('DELETE FROM items WHERE itemID = :id AND userID = :uid')
        ->execute([':id' => $itemId, ':uid' => $userId]);

    /* Log audit: item deleted */
    // Function = logAudit(), Class = data/database.php
    logAudit($pdo, $userId, 'deleted', "items: itemID=$itemId", "Deleted item #$itemId and associated images");

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    error_log('deleteListing error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'DB error']);
}