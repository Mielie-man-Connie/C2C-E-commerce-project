<?php
/* Process Order: handle escrow order placement (JSON API) */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../data/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in first.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get JSON request data
$input = json_decode(file_get_contents('php://input'), true);

$itemId = (int)($input['itemId'] ?? 0);
$quantity = (int)($input['quantity'] ?? 0);
$totalPrice = (float)($input['totalPrice'] ?? 0);

// Validate input
if ($itemId < 1 || $quantity < 1 || $totalPrice <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order parameters.']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get current item details and verify quantity available
    $stmt = $pdo->prepare('
        SELECT itemID, userID, itemsAvailable, itemPrice 
        FROM items 
        WHERE itemID = :id 
        LIMIT 1 
        FOR UPDATE
    ');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    // Validate item exists and user isn't buying their own item
    if (!$item) {
        throw new Exception('Item not found.');
    }

    if ($item['userID'] == $userId) {
        throw new Exception('You cannot purchase your own items.');
    }

    // Check if quantity is available
    if ($item['itemsAvailable'] < $quantity) {
        throw new Exception('Insufficient quantity available. Only ' . $item['itemsAvailable'] . ' left in stock.');
    }

    // CREATE ORDER RECORD - Removed 'quantity' column to match your schema precisely
    $stmt = $pdo->prepare('
        INSERT INTO orders (userID, itemID, orderDate, collectionDate)
        VALUES (:uid, :iid, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))
    ');
    $stmt->execute([
        ':uid' => $userId,
        ':iid' => $itemId
    ]);

    $orderId = (int)$pdo->lastInsertId();

    // CREATE ESCROW RECONCILIATION LOOP RECORD - Links directly to your escrow table
    $escrowStmt = $pdo->prepare('
        INSERT INTO escrow (orderID, escrowState, stateDate)
        VALUES (:oid, "Held in Escrow", NOW())
    ');
    $escrowStmt->execute([':oid' => $orderId]);

    // UPDATE ITEM QUANTITY (Deduct itemsAvailable from inventory table)
    $newQuantity = $item['itemsAvailable'] - $quantity;
    $stmt = $pdo->prepare('
        UPDATE items 
        SET itemsAvailable = :qty 
        WHERE itemID = :id
    ');
    $stmt->execute([
        ':qty' => $newQuantity,
        ':id' => $itemId
    ]);

    // LOG THE AUDIT TRAIL - Using your built-in database.php helper function!
    // Function = logAudit(), Class = data/database.php
    logAudit(
        $pdo, 
        $userId, 
        'ORDER_PLACED', 
        'Escrow Order Created', 
        'Order #' . $orderId . ' for item #' . $itemId . ' (Purchased quantity: ' . $quantity . ')'
    );

    // Commit transaction safely
    $pdo->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully.',
        'orderId' => $orderId,
        'newQuantity' => $newQuantity
    ]);

} catch (Exception $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Order processing error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}
?>