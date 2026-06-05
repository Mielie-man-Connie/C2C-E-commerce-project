<?php
    /* View Item: show item details and seller info */
    if (session_status() === PHP_SESSION_NONE) session_start();

    require_once __DIR__ . '/../data/database.php';

    $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$itemId || $itemId < 1) {
        header('Location: ../Browse/Browse.php');
        exit;
    }

    /* Fetch item + location */
    try {
        $stmt = $pdo->prepare(
            'SELECT i.itemID, i.userID, i.imageID AS imageJson,
                    i.itemName, i.itemDescription, i.itemPrice,
                    i.itemsAvailable, i.deliveryType,
                    l.province, l.city, l.suburb
            FROM items i
            LEFT JOIN locations l ON l.locationID = i.locationID
            WHERE i.itemID = :id LIMIT 1'
        );
        $stmt->execute([':id' => $itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $item = null;
        error_log('ViewItem fetch error: ' . $e->getMessage());
    }

    if (!$item) {
        /* Item not found — redirect with a flash message via query string */
        header('Location: ../Browse/Browse.php?error=item_not_found');
        exit;
    }

    /* ── Parse image IDs ── */
    $imageIds = json_decode($item['imageJson'] ?? '[]', true);
    if (!is_array($imageIds)) $imageIds = [];

    /* Fetch seller info */
    try {
        $s = $pdo->prepare(
            'SELECT username, name, surname, imageID AS sellerImageID,
                    locationID, mobile, isVerified, profileDescription, created_at
            FROM accounts WHERE userID = :uid LIMIT 1'
        );

        $s->execute([':uid' => (int)$item['userID']]);
        $seller = $s->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('ViewItem seller fetch error: ' . $e->getMessage());
        $seller = false;
    }

    if (!$seller) {
        $seller = [
            'username' => '',
            'name' => '',
            'surname' => '',
            'sellerImageID' => null,
            'mobile' => '',
            'isVerified' => false,
            'profileDescription' => '',
            'created_at' => null,
        ];
    }

    /* Fetch reviews placed on this item */
    $reviews = [];
    $avgScore = null;
    $reviewCount = 0;
    try {
        // Existing query fetching individual item reviews (Leave this as r.itemID = :iid)
        $stmt = $pdo->prepare(
            'SELECT r.reviewID, r.reviewScore, r.reviewTitle, r.reviewDescription, r.reviewDate,
                    a.username, a.name, a.surname
             FROM reviews r
             JOIN accounts a ON a.userID = r.userID
             WHERE r.itemID = :iid
             ORDER BY r.reviewDate DESC'
        );
        $stmt->execute([':iid' => $itemId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // FIX: INNER JOIN items to calculate metrics across all items created by this seller
        $stmt = $pdo->prepare('
            SELECT AVG(r.reviewScore) as avgScore, COUNT(*) as cnt 
            FROM reviews r
            INNER JOIN items i ON r.itemID = i.itemID
            WHERE i.userID = :uid
        ');
        $stmt->execute([':uid' => $item['userID']]);
        $scoreRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $avgScore = $scoreRow['avgScore'] ? round((float)$scoreRow['avgScore'], 1) : null;
        $reviewCount = (int)($scoreRow['cnt'] ?? 0);
    } catch (PDOException $e) {
        error_log('Review fetch error: ' . $e->getMessage());
    }

    /* ── Record in session view history (max 20) ── */
    if (!isset($_SESSION['view_history'])) {
        $_SESSION['view_history'] = [];
    }
    $_SESSION['view_history'] = array_values(
        array_unique(array_merge([$itemId], $_SESSION['view_history']))
    );
    if (count($_SESSION['view_history']) > 20) {
        $_SESSION['view_history'] = array_slice($_SESSION['view_history'], 0, 20);
    }

    /* ── Helpers ── */
    function h(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    function formatZAR(float $amount): string {
        return 'R&nbsp;' . number_format($amount, 2, '.', '&thinsp;');
    }

    $deliveryLabel = 'Pickup only';
    $deliveryIcon = '📦';
    if ($item['deliveryType'] === 'E') {
        $deliveryLabel = 'Escrow (secure payment)';
        $deliveryIcon = '🔒';
    } elseif ($item['deliveryType'] === 'A') {
        $deliveryLabel = 'Any';
        $deliveryIcon = '🔁';
    }

    $locParts = [];
    if (!empty($item['suburb'])) {
        $locParts[] = $item['suburb'];
    }
    if (!empty($item['city'])) {
        $locParts[] = $item['city'];
    }
    if (!empty($item['province'])) {
        $locParts[] = $item['province'];
    }
    $location = implode(', ', $locParts) ?: 'Location not specified';

    $sellerName = '';
    if (!empty($seller['name']) || !empty($seller['surname'])) {
        $sellerName = trim(($seller['name'] ?? '') . ' ' . ($seller['surname'] ?? ''));

    }
    if (empty($sellerName) && !empty($seller['username'])) {
        $sellerName = $seller['username'];
    }
    if (empty($sellerName)) {
        $sellerName = 'Unknown seller';
    }

    $sellerHandle = '@' . ($seller['username'] ?: 'unknown');
    $sellerVerified = !empty($seller['isVerified']);
    $memberSince = !empty($seller['created_at']) ? date('F Y', strtotime($seller['created_at'])) : 'Unknown';
    $sellerImgUrl = !empty($seller['sellerImageID']) ? '../data/getImage.php?id=' . (int)$seller['sellerImageID'] : null;
    $sellerPhone = !empty($seller['mobile']) ? $seller['mobile'] : 'Unknown phone';
    $sellerInitial = strtoupper(mb_substr($sellerName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= h($item['itemName']) ?> – TradeSA</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="ViewItem.css">
        <link rel="stylesheet" href="../data/preset.css">
        <script src="../data/translations.js"></script>
    </head>
    <body>
        <?php include '../HeaderAndFooter/Header.php'; ?>

        <div class="vi-page">
            <div class="vi-card">
                <!-- ── Breadcrumb ── -->
                <nav class="breadcrumb" aria-label="breadcrumb">
                    <a href="../Browse/Browse.php">Browse</a>
                    <span class="bc-sep">›</span>
                    <span><?= h($item['itemName']) ?></span>
                </nav>

                <div class="vi-layout">

                <!-- ════════════════════════════════
                    LEFT: Gallery
                    ════════════════════════════════ -->
                <div class="vi-gallery">

                    <!-- Main image -->
                    <div class="vi-main-img" id="mainImgWrap">
                        <?php if (!empty($imageIds)): ?>
                            <img src="../data/getImage.php?id=<?= (int)$imageIds[0] ?>"
                                alt="<?= h($item['itemName']) ?>"
                                id="mainImg"
                                class="main-img">
                        <?php else: ?>
                            <div class="main-img-placeholder">
                                <?= h(strtoupper(mb_substr($item['itemName'],0,1))) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Thumbnails strip (only if >1 image) -->
                    <?php if (count($imageIds) > 1): ?>
                    <div class="vi-thumbs" id="thumbsStrip">
                        <?php foreach ($imageIds as $idx => $imgId): ?>
                            <div class="vi-thumb <?= $idx === 0 ? 'active' : '' ?>"
                                data-src="../data/getImage.php?id=<?= (int)$imgId ?>"
                                data-idx="<?= $idx ?>"
                                tabindex="0"
                                role="button"
                                aria-label="Image <?= $idx+1 ?>">
                                <img src="../data/getImage.php?id=<?= (int)$imgId ?>"
                                    alt="Thumbnail <?= $idx+1 ?>"
                                    loading="lazy">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- ════════════════════════════════
                    RIGHT: Details + Seller
                    ════════════════════════════════ -->
                <div class="vi-details">

                    <!-- Title & price -->
                    <div class="vi-title-block">
                        <h1 class="vi-title"><?= h($item['itemName']) ?></h1>
                        <div class="vi-price">
                            <?= formatZAR((float)$item['itemPrice']) ?>
                        </div>
                    </div>

                    <!-- Quick stats -->
                    <div class="vi-stats">
                        <div class="vi-stat">
                            <span class="stat-icon">📍</span>
                            <span><?= h($location) ?></span>
                        </div>
                        <div class="vi-stat">
                            <span class="stat-icon"><?= $deliveryIcon ?></span>
                            <span><?= $deliveryLabel ?></span>
                        </div>
                        <div class="vi-stat">
                            <span class="stat-icon">📦</span>
                            <span>
                                <?php if ((int)$item['itemsAvailable'] <= 0): ?>
                                    <span style="color: #c00; font-weight: 600;">Out of Stock</span>
                                <?php else: ?>
                                    <?= (int)$item['itemsAvailable'] ?> available
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($item['itemDescription'])): ?>
                    <div class="vi-section">
                        <h3 class="vi-section-title">Description</h3>
                        <p class="vi-description"><?= nl2br(h($item['itemDescription'])) ?></p>
                    </div>
                    <?php endif; ?>

                    <!-- Action buttons -->
                    <div class="vi-actions">
                        <?php if ((int)$item['itemsAvailable'] <= 0): ?>
                            <button class="btn-buy" disabled style="opacity: 0.6; cursor: not-allowed;" data-i18n="outOfStock">
                                📦 Out of Stock
                            </button>
                        <?php elseif ($item['deliveryType'] === 'A' || $item['deliveryType'] === 'E'): ?>
                            <button class="btn-buy" onclick="openBuyModal()" data-i18n="buyViaEscrow">
                                🔒 Buy via Escrow
                            </button>
                        <?php endif; ?>
                        <button class="btn-contact" onclick="openContactModal()" data-i18n="contactSeller">
                            💬 Contact seller
                        </button>
                        <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] !== (int)$item['userID']): ?>
                            <a href="../Reviews/CreateReview.php?seller=<?= (int)$item['userID'] ?>&item=<?= (int)$itemId ?>" class="btn-contact" style="text-decoration: none; text-align: center;" data-i18n="leaveReview">
                                ⭐ Leave a Review
                            </a>
                        <?php endif; ?>
                        <a href="../CreateReport/CreateReport.php?itemID=<?= (int)$itemId ?>&sellerID=<?= (int)$item['userID'] ?>" class="btn-contact" style="text-decoration: none; text-align: center; background: #f3f4f6; color: #6b7280; border: 1px solid #d1d5db;">
                            ⚠️ Report Item
                        </a>
                    </div>

                    <!-- Seller card -->
                    <div class="seller-card">
                        <div class="seller-avatar">
                            <?php if ($sellerImgUrl): ?>
                                <img src="<?= h($sellerImgUrl) ?>" alt="<?= h($sellerName) ?>">
                            <?php else: ?>
                                <span><?= h($sellerInitial) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="seller-info">
                            <div class="seller-name-row">
                                <span class="seller-name"><?= h($sellerName) ?></span>
                                <?php if ($sellerVerified): ?>
                                    <span class="verified-badge">✓ Verified</span>
                                <?php endif; ?>
                            </div>
                            <span class="seller-handle"><?= h($sellerHandle) ?></span>
                            <span class="seller-since">Member since <?= h($memberSince) ?></span>
                            <?php if ($avgScore !== null): ?>
                                <div class="seller-rating" style="margin-top: 0.5rem;">
                                    <span style="color: #f59e0b;">★</span>
                                    <strong><?= number_format($avgScore, 1) ?>/10</strong>
                                    <span style="font-size: 0.85rem; color: #7a8a99;">(<?= $reviewCount ?> review<?= $reviewCount !== 1 ? 's' : '' ?>)</span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($seller['profileDescription'])): ?>
                                <p class="seller-bio"><?= h($seller['profileDescription']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                </div><!-- /.vi-details -->
                </div><!-- /.vi-layout -->

                <!-- Reviews Section -->
                <div class="vi-reviews-section">
                    <h2 class="vi-reviews-title" data-i18n="reviewsForThisSeller">Reviews for this seller</h2>
                    <?php if (!empty($reviews)): ?>
                        <div class="reviews-list">
                            <?php foreach ($reviews as $review): 
                                $reviewerName = '';
                                if (!empty($review['name']) || !empty($review['surname'])) {
                                    $reviewerName = trim(($review['name'] ?? '') . ' ' . ($review['surname'] ?? ''));
                                }
                                if (empty($reviewerName)) {
                                    $reviewerName = $review['username'] ?? 'Anonymous';
                                }
                            ?>
                                <div class="review-item">
                                    <div class="review-header">
                                        <div>
                                            <h3 class="review-title"><?= h($review['reviewTitle']) ?></h3>
                                            <p class="review-author">
                                                by <?= h($reviewerName) ?>
                                                <span class="review-date"><?= date('M d, Y', strtotime($review['reviewDate'])) ?></span>
                                            </p>
                                        </div>
                                        <span class="review-score"><?= (int)$review['reviewScore'] ?>/10</span>
                                    </div>
                                    <p class="review-description"><?= h($review['reviewDescription']) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #7a8a99; text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 8px;">
                            No reviews yet. Be the first to leave a review!
                        </p>
                    <?php endif; ?>
                </div>

            </div><!-- /.vi-card -->
        </div><!-- /.vi-page -->

        <!-- ════════════════════════════════
            MODAL: Contact seller
            ════════════════════════════════ -->
        <div class="vi-modal-overlay" id="contactOverlay" onclick="closeModal('contactOverlay')">
            <div class="vi-modal" onclick="event.stopPropagation()">
                <div class="vi-modal-head">
                    <h2>Contact seller</h2>
                    <button class="vi-modal-close" onclick="closeModal('contactOverlay')">✕</button>
                </div>
                <div class="vi-modal-body">
                    <p class="modal-intro">To contact <strong><?= h($sellerName) ?></strong>, please use one of the options below.</p>
                    <div class="contact-info">
                        <p><strong>Phone:</strong> <?= h($sellerPhone) ?></p>
                        <p>You can contact the seller through an external website, SMS, or by calling directly.</p>
                    </div>
                    <div class="contact-actions">
                        <a href="https://www.example.com" target="_blank" rel="noopener" class="btn-modal-submit">External contact site</a>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $sellerPhone) ?>" class="btn-modal-submit btn-secondary">Call or SMS seller</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════
            MODAL: Buy via Escrow
            ════════════════════════════════ -->
        <div class="vi-modal-overlay" id="buyOverlay" onclick="closeModal('buyOverlay')">
            <div class="vi-modal" onclick="event.stopPropagation()">
                <div class="vi-modal-head">
                    <h2>Buy via Escrow</h2>
                    <button class="vi-modal-close" onclick="closeModal('buyOverlay')">✕</button>
                </div>
                <div class="vi-modal-body">
                    <div class="escrow-info">
                        <div class="escrow-step"><span class="es-num">1</span><span>Select quantity and proceed to payment.</span></div>
                        <div class="escrow-step"><span class="es-num">2</span><span>You pay TradeSA — funds held securely.</span></div>
                        <div class="escrow-step"><span class="es-num">3</span><span>Seller ships the item to you.</span></div>
                        <div class="escrow-step"><span class="es-num">4</span><span>You confirm receipt — seller gets paid.</span></div>
                    </div>
                    
                    <!-- Quantity Selector -->
                    <div style="margin: 1.2rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                        <label for="buyQty" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #1c2331;">
                            Quantity (max <?= (int)$item['itemsAvailable'] ?>)
                        </label>
                        <input type="number" id="buyQty" min="1" max="<?= (int)$item['itemsAvailable'] ?>" value="1" 
                            style="width: 100%; padding: 0.6rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;">
                    </div>

                    <div class="escrow-total">
                        Total: <strong id="buyTotal"><?= formatZAR((float)$item['itemPrice']) ?></strong>
                    </div>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="btn-modal-submit" onclick="proceedToBuy()">
                            Proceed to payment
                        </button>
                    <?php else: ?>
                        <a href="../LoginAndRegister/LoginPage.php" class="btn-modal-submit" style="text-align:center;display:block;text-decoration:none">
                            Log in to purchase
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php include '../HeaderAndFooter/Footer.php'; ?>

        <script>
        /* ── Image gallery ── */
        const mainImg   = document.getElementById('mainImg');
        const thumbs    = document.querySelectorAll('.vi-thumb');

        thumbs.forEach(thumb => {
            thumb.addEventListener('click',   () => switchImg(thumb));
            thumb.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') switchImg(thumb); });
        });

        function switchImg(thumb) {
            if (!mainImg) return;
            mainImg.src = thumb.dataset.src;
            thumbs.forEach(t => t.classList.remove('active'));
            thumb.classList.add('active');
        }

        /* ── Quantity calculator for buy modal ── */
        const qtyInput = document.getElementById('buyQty');
        const buyTotalEl = document.getElementById('buyTotal');
        const itemPrice = <?= (float)$item['itemPrice'] ?>;

        if (qtyInput) {
            qtyInput.addEventListener('change', updateBuyTotal);
            qtyInput.addEventListener('keyup', updateBuyTotal);
        }

        function updateBuyTotal() {
            if (!buyTotalEl || !qtyInput) return;
            const qty = Math.max(1, Math.min(<?= (int)$item['itemsAvailable'] ?>, parseInt(qtyInput.value) || 1));
            qtyInput.value = qty;
            const total = (qty * itemPrice).toFixed(2);
            buyTotalEl.textContent = 'R ' + parseFloat(total).toLocaleString('en-ZA', {minimumFractionDigits: 2});
        }

        /* ── Proceed to buy ── */
        function proceedToBuy() {
            const qty = parseInt(qtyInput.value) || 1;
            const itemId = <?= (int)$itemId ?>;
            const itemName = '<?= urlencode($item['itemName']) ?>';
            const itemPrice = <?= (float)$item['itemPrice'] ?>;
            
            window.location.href = '../Escrow/EscrowSimulator.php?itemID=' + itemId + '&itemName=' + itemName + '&itemPrice=' + itemPrice + '&quantity=' + qty;
        }

        /* ── Modals ── */
        function openContactModal() { openModal('contactOverlay'); }
        function openBuyModal()     { openModal('buyOverlay'); updateBuyTotal(); }

        function openModal(id) {
            document.getElementById(id).classList.add('open');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape')
                document.querySelectorAll('.vi-modal-overlay.open').forEach(el => closeModal(el.id));
        });

        /* ── Contact form stub ── */
        function submitContact(e) {
            e.preventDefault();
            showToast('Contact the seller using the options shown.');
            closeModal('contactOverlay');
        }

        /* ── Toast (reuse header toast if present, else create one) ── */
        function showToast(msg) {
            let t = document.getElementById('hdrToast');
            if (!t) {
                t = document.createElement('div');
                t.id = 'viToast';
                t.style.cssText = 'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%) translateY(30px);background:#141924;color:#fff;padding:.65rem 1.3rem;border-radius:999px;font-size:.88rem;opacity:0;transition:opacity .25s,transform .25s;z-index:9999;pointer-events:none';
                document.body.appendChild(t);
            }
            t.textContent = msg;
            t.style.opacity = '1';
            t.style.transform = 'translateX(-50%) translateY(0)';
            setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(-50%) translateY(30px)'; }, 3000);
        }

        /* ── Initialize language on page load ── */
        window.addEventListener('load', () => {
            if (window.applyPageLanguage) {
                window.applyPageLanguage(window.getCurrentLanguage());
            }
            
            // Listen for header language changes
            const headerLangSelect = document.getElementById('header-language-select');
            if (headerLangSelect) {
                headerLangSelect.addEventListener('change', function() {
                    window.applyPageLanguage(this.value);
                });
            }
        });
        </script>
    </body>
</html>