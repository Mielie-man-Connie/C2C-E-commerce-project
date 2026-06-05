<?php
/* CreateReview.php - PHP */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../LoginAndRegister/LoginPage.php');
    exit;
}

require_once __DIR__ . '/../data/database.php';

$currentUserId = (int)$_SESSION['user_id'];
$sellerUserId = filter_input(INPUT_GET, 'seller', FILTER_VALIDATE_INT);
$itemId = filter_input(INPUT_GET, 'item', FILTER_VALIDATE_INT);
$errors = [];
$success = '';

/* Validate seller and item */
if (!$sellerUserId || !$itemId) {
    header('Location: ../Browse/Browse.php');
    exit;
}

/* Prevent self-reviews */
if ($currentUserId === $sellerUserId) {
    $errors[] = 'You cannot review your own items.';
}

/* Get item name for display */
$itemName = 'Item';
try {
    $stmt = $pdo->prepare('SELECT itemName FROM items WHERE itemID = :id LIMIT 1');
    $stmt->execute([':id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($item) $itemName = htmlspecialchars($item['itemName'], ENT_QUOTES, 'UTF-8');
} catch (PDOException $e) {}

/* Check if user already reviewed this seller+item */
try {
    $stmt = $pdo->prepare(
        'SELECT reviewID FROM reviews 
         WHERE userID = :uid AND itemID = :iid 
         LIMIT 1'
    );
    /* FIX 1: Check if the CURRENT user has already left a review */
    $stmt->execute([':uid' => $currentUserId, ':iid' => $itemId]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'You have already reviewed this item.';
    }
} catch (PDOException $e) {}

/* Handle form submission */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $score = filter_input(INPUT_POST, 'score', FILTER_VALIDATE_INT);

    /* Validation */
    if (!$title) {
        $errors[] = 'Review title is required.';
    } elseif (strlen($title) > 100) {
        $errors[] = 'Review title must be 100 characters or less.';
    }

    if (!$description) {
        $errors[] = 'Review description is required.';
    } elseif (strlen($description) > 500) {
        $errors[] = 'Review description must be 500 characters or less.';
    }

    if (!$score || $score < 1 || $score > 10) {
        $errors[] = 'Please select a rating between 1 and 10.';
    }

    /* Save review if no errors */
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO reviews (userID, itemID, reviewScore, reviewTitle, reviewDescription, reviewDate)
                 VALUES (:uid, :iid, :score, :title, :desc, NOW())'
            );
            $stmt->execute([
                ':uid' => $currentUserId, /* FIX 2: Save the logged-in user ID as the reviewer */
                ':iid' => $itemId,
                ':score' => $score,
                ':title' => $title,
                ':desc' => $description
            ]);
            
            $success = 'Review submitted successfully!';
            $title = '';
            $description = '';
            $score = '';
        } catch (PDOException $e) {
            $errors[] = 'Failed to save review. Please try again.';
            error_log('Review save error: ' . $e->getMessage());
        }
    }
}
?>

<!-- CreateReview.php - HTML -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave a Review - TradeSA</title>
    <link rel="stylesheet" href="../data/preset.css">
    <link rel="stylesheet" href="Reviews.css">
    <script src="../data/translations.js"></script>
</head>
<body>
<?php include '../HeaderAndFooter/Header.php'; ?>
<div class="review-container">
    <div class="review-card">
        <h1>Leave a Review</h1>
        <p class="review-subtitle">Share your experience with this seller for item: <strong><?= $itemName ?></strong></p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $err): ?>
                    <p>• <?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if (empty($errors) || $success): ?>
            <form method="POST" class="review-form">
                <!-- Rating (1-10) -->
                <div class="form-group">
                    <label for="score" data-i18n="rating">Rating *</label>
                    <div class="rating-selector">
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                            <input type="radio" id="score<?= $i ?>" name="score" value="<?= $i ?>" required>
                            <label for="score<?= $i ?>" class="rating-label"><?= $i ?></label>
                        <?php endfor; ?>
                    </div>
                </div>

                <!-- Title -->
                <div class="form-group">
                    <label for="title" data-i18n="reviewTitle">Title *</label>
                    <input type="text" id="title" name="title" maxlength="100" 
                           placeholder="e.g., Great seller, fast shipping" required
                           value="<?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <small data-i18n="max100Chars">Max 100 characters</small>
                </div>

                <!-- Description -->
                <div class="form-group">
                    <label for="description" data-i18n="description">Description *</label>
                    <textarea id="description" name="description" maxlength="500" 
                              placeholder="Tell us about your experience..." required
                              rows="5"><?= htmlspecialchars($description ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    <small data-i18n="max500Chars">Max 500 characters</small>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit" data-i18n="submitReview">Submit Review</button>
                <a href="<?= htmlspecialchars('javascript:history.back()', ENT_QUOTES, 'UTF-8') ?>" class="btn-cancel" data-i18n="cancel">Cancel</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../HeaderAndFooter/Footer.php'; ?>

<script>
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
