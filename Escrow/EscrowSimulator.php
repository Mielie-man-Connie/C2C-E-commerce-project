<?php
/* Escrow: checkout simulator and order processing */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../data/database.php';

// Get parameters from query string
$itemId    = filter_input(INPUT_GET, 'itemID', FILTER_VALIDATE_INT) ?: 0;
$itemName  = trim(filter_input(INPUT_GET, 'itemName', FILTER_SANITIZE_STRING) ?? 'Unknown item');
$itemPrice = filter_input(INPUT_GET, 'itemPrice', FILTER_VALIDATE_FLOAT);
$quantity  = filter_input(INPUT_GET, 'quantity', FILTER_VALIDATE_INT) ?: 1;

if ($itemPrice === false || $itemPrice === null) {
    $itemPrice = 0.00;
}

// Check if user is logged in
$userId = $_SESSION['user_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Escrow Simulator — TradeSA</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="data/preset.css">
    <style>
        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            background: #eef0f4;
            color: #1c2331;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .escrow-shell {
            max-width: 560px;
            width: 100%;
            background: var(--ghost-white);
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(0,0,0,0.12);
            border: 1px solid rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .escrow-header {
            padding: 1.6rem 1.8rem;
            background: linear-gradient(135deg, #149079, #094d40);
            color: white;
        }
        .escrow-header h1 { margin: 0; font-size: 1.5rem; }
        .escrow-body { padding: 1.6rem 1.8rem; }
        .escrow-info { margin-bottom: 1.3rem; }
        .escrow-info h2 { margin: 0 0 0.5rem; font-size: 1rem; color: #141924; }
        .escrow-info p { margin: 0.3rem 0; color: #4a525f; line-height: 1.6; }
        .order-card {
            padding: 1rem 1rem;
            background: #f8f9fa;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.08);
            margin-bottom: 1.4rem;
        }
        .order-card strong { color: #141924; }
        .escrow-actions { display: flex; gap: 0.9rem; flex-wrap: wrap; }
        .btn-primary, .btn-secondary {
            width: 100%;
            padding: 0.95rem 1rem;
            border-radius: 14px;
            border: none;
            font-size: 0.98rem;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-primary {
            color: white;
            background: linear-gradient(135deg, #149079, #094d40);
        }
        .btn-secondary {
            color: #094d40;
            background: #f8fafc;
            border: 1px solid rgba(9,77,64,0.16);
        }
        .escrow-success {
            display: none;
            padding: 1.3rem 1.3rem;
            background: #ecfdf5;
            border: 1px solid #d1fad7;
            border-radius: 16px;
            margin-top: 1rem;
            color: #065f46;
        }
        .escrow-error {
            display: none;
            padding: 1.3rem 1.3rem;
            background: #fee;
            border: 1px solid #fcc;
            border-radius: 16px;
            margin-top: 1rem;
            color: #c00;
        }
        .escrow-success h2, .escrow-error h2 {
            margin: 0 0 0.8rem;
            font-size: 1.2rem;
        }
        .escrow-success p, .escrow-error p {
            margin: 0.5rem 0;
            line-height: 1.6;
        }
        .escrow-success a, .escrow-error a {
            color: inherit;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="escrow-shell">
    <div class="escrow-header">
        <h1>Escrow Checkout</h1>
    </div>
    <div class="escrow-body">
        <div class="escrow-info">
            <h2>Order Summary</h2>
            <div class="order-card">
                <p><strong>Item:</strong> <?= htmlspecialchars($itemName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
                <p><strong>Quantity:</strong> <?= (int)$quantity ?></p>
                <p><strong>Unit Price:</strong> R <?= number_format($itemPrice, 2, '.', ',') ?></p>
                <p><strong>Total:</strong> R <?= number_format($itemPrice * $quantity, 2, '.', ',') ?></p>
                <p><strong>Status:</strong> Pending secure payment</p>
            </div>
            <p style="font-size: 0.9rem; color: #7a8a99;">
                Your payment will be securely processed through our escrow system. The seller will receive payment only after you confirm receipt of the item.
            </p>
        </div>
        <div class="escrow-actions">
            <?php if ($userId): ?>
                <button class="btn-primary" id="placeOrderBtn">Complete Purchase (R <?= number_format($itemPrice * $quantity, 2) ?>)</button>
            <?php else: ?>
                <p style="color: #c00; font-weight: 500;">Please <a href="../LoginAndRegister/LoginPage.php">log in</a> to complete your purchase.</p>
                <a href="../Browse/Browse.php" class="btn-secondary">Back to Browse</a>
            <?php endif; ?>
            <a href="../ViewItem/ViewItem.php?id=<?= (int)$itemId ?>" class="btn-secondary">Back to Item</a>
        </div>
        <div class="escrow-success" id="escrowSuccess">
            <h2>✓ Order Successfully Placed</h2>
            <p>Your order has been confirmed and processed through our secure escrow system.</p>
            <p><strong>Order ID:</strong> <span id="orderId">-</span></p>
            <p>You can track this order in your <a href="../History/History.php" style="color: #149079; font-weight: 500;">purchase history</a>.</p>
            <p>Thank you for using TradeSA!</p>
            <a href="../Browse/Browse.php" class="btn-primary" style="display: inline-block; margin-top: 1rem;">Continue Shopping</a>
        </div>
        <div class="escrow-error" id="escrowError">
            <h2>Order Failed</h2>
            <p id="errorMessage">There was an error processing your order. Please try again.</p>
            <a href="../ViewItem/ViewItem.php?id=<?= (int)$itemId ?>" class="btn-secondary">Back to Item</a>
        </div>
    </div>
</div>

<script>
    const btn = document.getElementById('placeOrderBtn');
    const success = document.getElementById('escrowSuccess');
    const error = document.getElementById('escrowError');

    if (btn) {
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            btn.textContent = 'Processing payment...';

            try {
                // Send order to server via AJAX
                const response = await fetch('ProcessOrder.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        itemId: <?= (int)$itemId ?>,
                        quantity: <?= (int)$quantity ?>,
                        totalPrice: <?= (float)($itemPrice * $quantity) ?>
                    })
                });

                const data = await response.json();

                if (data.success) {
                    btn.style.display = 'none';
                    document.getElementById('orderId').textContent = data.orderId;
                    success.style.display = 'block';
                } else {
                    btn.style.display = 'none';
                    document.getElementById('errorMessage').textContent = data.message || 'Unknown error occurred.';
                    error.style.display = 'block';
                }
            } catch (err) {
                console.error('Error:', err);
                btn.style.display = 'none';
                document.getElementById('errorMessage').textContent = 'Network error. Please try again.';
                error.style.display = 'block';
            }
        });
    }
</script>
</body>
</html>

