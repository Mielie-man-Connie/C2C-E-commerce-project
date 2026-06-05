<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../LoginAndRegister/LoginPage.php');
    exit;
}
require_once __DIR__ . '/../data/database.php';

$userId = (int) $_SESSION['user_id'];

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function formatZAR(float $v): string {
    return 'R ' . number_format($v, 2, '.', ' ');
}

/* ── My Listings ── */
try {
    $stmt = $pdo->prepare(
        'SELECT i.itemID, i.itemName, i.itemDescription, i.itemPrice,
                i.itemsAvailable, i.deliveryType, i.imageID AS imageJson,
                l.suburb, l.city, l.province
         FROM items i
         LEFT JOIN locations l ON l.locationID = i.locationID
         WHERE i.userID = :uid
         ORDER BY i.itemID DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $myItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $myItems = [];
}

/* Parse first imageID for each listing */
foreach ($myItems as &$it) {
    $ids = json_decode($it['imageJson'] ?? '[]', true);
    $it['firstImageID'] = (!empty($ids) && is_array($ids)) ? (int)$ids[0] : null;
}
unset($it);

/* ── My Orders ── */
try {
    $stmt = $pdo->prepare(
        'SELECT o.orderID, o.orderDate, o.collectionDate,
                i.itemID, i.itemName, i.itemPrice, i.imageID AS imageJson
         FROM orders o
         JOIN items i ON i.itemID = o.itemID
         WHERE o.userID = :uid
         ORDER BY o.orderDate DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $myOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $myOrders = [];
}

foreach ($myOrders as &$ord) {
    $ids = json_decode($ord['imageJson'] ?? '[]', true);
    $ord['firstImageID'] = (!empty($ids) && is_array($ids)) ? (int)$ids[0] : null;

    /* Fetch escrow states for this order */
    try {
        $es = $pdo->prepare(
            'SELECT escrowState, stateDate FROM escrow
             WHERE orderID = :oid ORDER BY stateDate ASC'
        );
        $es->execute([':oid' => $ord['orderID']]);
        $ord['escrowStates'] = $es->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $ord['escrowStates'] = [];
    }
}
unset($ord);

/* ── Reviews on my items ── */
$reviewsOnMyItems = [];
try {
    $stmt = $pdo->prepare(
        'SELECT r.reviewID, r.itemID, r.reviewScore, r.reviewTitle, r.reviewDescription, r.reviewDate,
                a.username, a.name, a.surname,
                i.itemName
         FROM reviews r
         JOIN accounts a ON a.userID = r.userID
         JOIN items i ON i.itemID = r.itemID
         WHERE i.userID = :uid
         ORDER BY r.reviewDate DESC'
    );
    $stmt->execute([':uid' => $userId]);
    $reviewsOnMyItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reviewsOnMyItems = [];
}

$activeTab = $_GET['tab'] ?? 'listings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TradeSA – History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="History.css">
    <link rel="stylesheet" href="../data/preset.css">
</head>
<body>
<?php include '../HeaderAndFooter/Header.php'; ?>

<div class="history-page">

    <div class="page-header">
        <h1>My Account</h1>
        <p class="page-sub">Manage your listings and track your orders.</p>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <button class="tab-btn <?= $activeTab === 'listings' ? 'active' : '' ?>"
                onclick="switchTab('listings')">
            My Listings
            <span class="tab-count"><?= count($myItems) ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'orders' ? 'active' : '' ?>"
                onclick="switchTab('orders')">
            My Orders
            <span class="tab-count"><?= count($myOrders) ?></span>
        </button>
        <button class="tab-btn <?= $activeTab === 'reviews' ? 'active' : '' ?>"
                onclick="switchTab('reviews')">
            Reviews on my items
            <span class="tab-count"><?= count($reviewsOnMyItems) ?></span>
        </button>
    </div>

    <!-- ══ MY LISTINGS ══ -->
    <section class="tab-panel <?= $activeTab === 'listings' ? 'active' : '' ?>" id="panel-listings">

        <div class="panel-toolbar">
            <p class="panel-desc">Items you have listed for sale.</p>
            <a href="../CreateListing/CreateListing.php" class="btn-primary">+ New listing</a>
        </div>

        <?php if (empty($myItems)): ?>
            <div class="empty-state">
                <div class="es-icon">📦</div>
                <div class="es-title">No listings yet</div>
                <div class="es-sub">Start selling by creating your first listing.</div>
                <a href="../CreateListing/CreateListing.php" class="btn-primary" style="margin-top:1rem">Create listing</a>
            </div>
        <?php else: ?>
            <div class="listings-grid">
                <?php foreach ($myItems as $item): ?>
                <div class="listing-card" id="item-<?= $item['itemID'] ?>">
                    <div class="lc-thumb">
                        <?php if ($item['firstImageID']): ?>
                            <img src="../data/getImage.php?id=<?= $item['firstImageID'] ?>"
                                 alt="<?= h($item['itemName']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="lc-thumb-placeholder">
                                <?= h(strtoupper(mb_substr($item['itemName'],0,1))) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="lc-info">
                        <div class="lc-name"><?= h($item['itemName']) ?></div>
                        <div class="lc-price"><?= formatZAR((float)$item['itemPrice']) ?></div>
                        <div class="lc-meta">
                            <?php if ($item['suburb'] || $item['city']): ?>
                                <span>📍 <?= h($item['suburb'] ?: $item['city']) ?></span>
                            <?php endif; ?>
                            <span><?= $item['itemsAvailable'] ?> available</span>
                            <span class="delivery-tag <?= $item['deliveryType'] === 'E' ? 'escrow' : 'pickup' ?>">
                                <?= $item['deliveryType'] === 'E' ? 'Escrow' : 'Pickup' ?>
                            </span>
                        </div>
                    </div>
                    <div class="lc-actions">
                        <a href="../CreateListing/CreateListing.php?id=<?= $item['itemID'] ?>"
                           class="lc-btn edit" title="Edit listing">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                        <button class="lc-btn delete" title="Delete listing"
                                onclick="confirmDelete(<?= $item['itemID'] ?>, '<?= h(addslashes($item['itemName'])) ?>')">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Delete
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ══ MY ORDERS ══ -->
    <section class="tab-panel <?= $activeTab === 'orders' ? 'active' : '' ?>" id="panel-orders">

        <div class="panel-toolbar">
            <p class="panel-desc">Items you have purchased through TradeSA.</p>
        </div>

        <?php if (empty($myOrders)): ?>
            <div class="empty-state">
                <div class="es-icon">🛒</div>
                <div class="es-title">No orders yet</div>
                <div class="es-sub">Items you buy will appear here.</div>
                <a href="../Browse/Browse.php" class="btn-primary" style="margin-top:1rem">Browse items</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($myOrders as $order):
                    $collected = !empty($order['collectionDate']);
                    $stateCount = count($order['escrowStates']);
                ?>
                <div class="order-card <?= $collected ? 'collected' : '' ?>">

                    <div class="oc-summary" onclick="toggleOrder(<?= $order['orderID'] ?>)">
                        <div class="oc-thumb">
                            <?php if ($order['firstImageID']): ?>
                                <img src="../data/getImage.php?id=<?= $order['firstImageID'] ?>"
                                     alt="<?= h($order['itemName']) ?>" loading="lazy">
                            <?php else: ?>
                                <div class="oc-thumb-placeholder">
                                    <?= h(strtoupper(mb_substr($order['itemName'],0,1))) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="oc-info">
                            <div class="oc-name"><?= h($order['itemName']) ?></div>
                            <div class="oc-meta">
                                <span>Ordered <?= date('d M Y', strtotime($order['orderDate'])) ?></span>
                                <span><?= $stateCount ?> escrow update<?= $stateCount !== 1 ? 's' : '' ?></span>
                            </div>
                            <div class="oc-price"><?= formatZAR((float)$order['itemPrice']) ?></div>
                        </div>
                        <div class="oc-right">
                            <?php if ($collected): ?>
                                <div class="collected-badge">✓ Collected</div>
                            <?php else: ?>
                                <div class="in-progress-badge">In progress</div>
                            <?php endif; ?>
                            <svg class="oc-chevron" width="18" height="18" fill="none"
                                 stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Expandable escrow timeline -->
                    <div class="oc-detail" id="order-detail-<?= $order['orderID'] ?>">
                        <div class="escrow-timeline">
                            <?php if (empty($order['escrowStates'])): ?>
                                <p class="no-escrow">No escrow updates yet. The seller will update this shortly.</p>
                            <?php else: ?>
                                <?php foreach ($order['escrowStates'] as $i => $state): ?>
                                <div class="et-step <?= $i === $stateCount - 1 ? 'current' : 'done' ?>">
                                    <div class="et-dot"></div>
                                    <div class="et-content">
                                        <div class="et-state"><?= h($state['escrowState']) ?></div>
                                        <div class="et-date"><?= date('d M Y, H:i', strtotime($state['stateDate'])) ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if ($collected): ?>
                                <div class="et-step collected">
                                    <div class="et-dot"></div>
                                    <div class="et-content">
                                        <div class="et-state">✓ Collected</div>
                                        <div class="et-date"><?= date('d M Y', strtotime($order['collectionDate'])) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ══ REVIEWS ON MY ITEMS ══ -->
    <section class="tab-panel <?= $activeTab === 'reviews' ? 'active' : '' ?>" id="panel-reviews">
        <div class="panel-toolbar">
            <p class="panel-desc">Reviews that buyers left on your items.</p>
        </div>

        <?php if (empty($reviewsOnMyItems)): ?>
            <div class="empty-state">
                <div class="es-icon">⭐</div>
                <div class="es-title">No reviews yet</div>
                <div class="es-sub">When buyers leave reviews on your items, they'll appear here.</div>
            </div>
        <?php else: ?>
            <div class="reviews-list">
                <?php foreach ($reviewsOnMyItems as $review): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="review-content">
                            <h3 class="review-title"><?= h($review['reviewTitle']) ?></h3>
                            <p class="review-meta">
                                <strong>From:</strong> <?= h($review['name'] . ' ' . $review['surname']) ?>
                                <strong style="margin-left: 1rem;">On:</strong> <?= h($review['itemName']) ?>
                            </p>
                            <p class="review-date"><?= date('d M Y', strtotime($review['reviewDate'])) ?></p>
                        </div>
                        <span class="review-score"><?= (int)$review['reviewScore'] ?>/10</span>
                    </div>
                    <p class="review-text"><?= h($review['reviewDescription']) ?></p>
                    <a href="../ViewItem/ViewItem.php?id=<?= (int)$review['itemID'] ?>" class="review-link">
                        View item →
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

</div>

<!-- ══ DELETE CONFIRMATION MODAL ══ -->
<div class="modal-overlay" id="deleteModal" onclick="closeDeleteModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-icon">🗑️</div>
        <h2 class="modal-title">Delete listing?</h2>
        <p class="modal-body" id="deleteModalBody">Are you sure you want to delete this listing? This cannot be undone.</p>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-confirm-delete" id="confirmDeleteBtn">Yes, delete</button>
        </div>
    </div>
</div>

<?php include '../HeaderAndFooter/Footer.php'; ?>

<script>
/* ── Tab switching ── */
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`[onclick="switchTab('${tab}')"]`).classList.add('active');
    document.getElementById('panel-' + tab).classList.add('active');
    history.replaceState(null, '', '?tab=' + tab);
}

/* ── Order expand/collapse ── */
function toggleOrder(id) {
    const el = document.getElementById('order-detail-' + id);
    el.classList.toggle('open');
    const card = el.closest('.order-card');
    card.querySelector('.oc-chevron').style.transform =
        el.classList.contains('open') ? 'rotate(180deg)' : '';
}

/* ── Delete flow ── */
let pendingDeleteId = null;

function confirmDelete(itemId, itemName) {
    pendingDeleteId = itemId;
    document.getElementById('deleteModalBody').textContent =
        `Are you sure you want to delete "${itemName}"? This cannot be undone.`;
    document.getElementById('deleteModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
    document.body.style.overflow = '';
    pendingDeleteId = null;
}

document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
    if (!pendingDeleteId) return;
    const btn = this;
    btn.disabled = true;
    btn.textContent = 'Deleting…';

    fetch('../data/deleteListing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'itemID=' + pendingDeleteId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('item-' + pendingDeleteId);
            card.style.transition = 'opacity 0.3s, transform 0.3s';
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => card.remove(), 320);
            closeDeleteModal();
        } else {
            alert('Error: ' + (data.error || 'Could not delete listing.'));
        }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Yes, delete';
    });
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeDeleteModal();
});
</script>
</body>
</html>