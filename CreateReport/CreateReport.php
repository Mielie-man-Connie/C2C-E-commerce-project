<?php
/* Create Report: user report form and handler */
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../LoginAndRegister/LoginPage.php');
    exit;
}

require_once __DIR__ . '/../data/database.php';

$userId      = (int)$_SESSION['user_id'];
$prefillItem = filter_input(INPUT_GET, 'itemID',   FILTER_VALIDATE_INT) ?: null;
$prefillSeller = filter_input(INPUT_GET, 'sellerID', FILTER_VALIDATE_INT) ?: null;
$errors   = [];
$success  = '';

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ── Load all accounts (for user search) ── */
$allUsers = [];
try {
    $allUsers = $pdo->query(
        'SELECT userID, username, name, surname FROM accounts ORDER BY username'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* ── Load all items (for item search) ── */
$allItems = [];
try {
    $allItems = $pdo->query(
        'SELECT itemID, userID, itemName FROM items ORDER BY itemName'
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

/* ── Pre-fill data ── */
$prefillItemName   = '';
$prefillSellerName = '';
if ($prefillItem) {
    $prefillItemName = '';
    foreach ($allItems as $i) {
        if ((int)$i['itemID'] === $prefillItem) { $prefillItemName = $i['itemName'] ?? ''; break; }
    }
}
if ($prefillSeller) {
    $prefillSellerName = '';
    foreach ($allUsers as $u) {
        if ((int)$u['userID'] === $prefillSeller) { $prefillSellerName = $u['username'] ?? ''; break; }
    }
}

$returnUrl = trim((string)filter_input(INPUT_GET, 'return', FILTER_SANITIZE_URL));
if ($returnUrl && substr($returnUrl, 0, 1) !== '/') {
    $returnUrl = ''; // only allow relative paths to avoid external redirects
}
$returnParam = $returnUrl ? ('?return=' . rawurlencode($returnUrl)) : '';

$reportType = trim((string)filter_input(INPUT_GET, 'reportType', FILTER_SANITIZE_STRING));

/* ── POST handler ── */
$fTitle       = '';
$fDesc        = '';
$fCategory    = '';
$fRepUserID   = $prefillSeller ?? '';
$fRepItemID   = $prefillItem ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fTitle      = trim($_POST['reportTitle']       ?? '');
    $fDesc       = trim($_POST['reportDescription'] ?? '');
    $fCategory   = trim($_POST['category']          ?? '');
    $fRepUserID  = filter_var($_POST['reportedUserID'] ?? '', FILTER_VALIDATE_INT) ?: null;
    $fRepItemID  = filter_var($_POST['reportedItemID'] ?? '', FILTER_VALIDATE_INT) ?: null;

    if (!$fTitle)    $errors[] = 'A report title is required.';
    if (!$fDesc)     $errors[] = 'Please describe the issue.';
    if (!$fCategory) $errors[] = 'Please select an issue type.';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO reports (userID, reportTitle, reportDescription, reportedUserID, reportedItemID)
                 VALUES (:uid, :title, :desc, :ruid, :riid)'
            );
            $stmt->execute([
                ':uid'   => $userId,
                ':title' => $fTitle,
                ':desc'  => $fDesc,
                ':ruid'  => $fRepUserID ?: null,
                ':riid'  => $fRepItemID ?: null,
            ]);

            $newReportId = (int)$pdo->lastInsertId();
            $auditDesc = "Category: {$fCategory}.";
            if ($fRepUserID) $auditDesc .= " Reported userID: {$fRepUserID}.";
            if ($fRepItemID) $auditDesc .= " Reported itemID: {$fRepItemID}.";
            // Function = logAudit(), Class = data/database.php
            logAudit($pdo, $userId, 'created', "reports: reportID=$newReportId", $auditDesc);

            $success = 'Your report has been submitted. Our team will review it shortly.';
            $fTitle = $fDesc = $fCategory = '';
            $fRepUserID = $fRepItemID = null;
        } catch (PDOException $e) {
            $errors[] = 'DB error: ' . $e->getMessage();
        }
    }
}

$usersJson = json_encode($allUsers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$itemsJson = json_encode($allItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TradeSA – Report an Issue</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="createReport.css">
    <link rel="stylesheet" href="../data/preset.css">
</head>
<body>
<?php include '../HeaderAndFooter/Header.php'; ?>

<div class="cr-page">
    <div class="cr-panel">

        <div class="cr-header">
            <div class="cr-eyebrow">TradeSA</div>
            <h1>Report an Issue</h1>
            <p class="cr-sub">All reports are reviewed by our admin team. Please be as specific as possible.</p>
        </div>
        <div class="cr-mini-card">
            <strong>Missing location in the list?</strong>
            <p>If you cannot find the correct province, city or suburb, report it here and we will update the list.</p>
            <button type="button" id="reportLocationBtn" class="btn-secondary">Report missing location</button>
        </div>

        <?php if ($success): ?>
            <div class="cr-feedback success">
                <span class="fb-icon">✓</span>
                <div>
                    <strong>Report submitted</strong>
                    <p><?= h($success) ?></p>
                </div>
            </div>
            <a href="<?= $returnUrl ? h($returnUrl) : '../Browse/Browse.php' ?>" class="btn-back">← Back</a>

        <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="cr-feedback error">
                <?php foreach ($errors as $e): ?><p>⚠ <?= h($e) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="createReport.php<?= $returnParam ?><?= ($prefillItem||$prefillSeller) ? ($returnParam ? '&' : '?').'itemID='.($prefillItem??'').'&sellerID='.($prefillSeller??'') : '' ?>"
              method="POST" id="reportForm" novalidate>

            <!-- ── Issue type ── -->
            <div class="cr-section">
                <div class="cr-section-title">What type of issue is this?</div>
                <div class="radio-grid">
                    <?php
                    $cats = [
                        ['bug',           '🐛', 'Bug / Technical problem'],
                        ['scam',          '🚨', 'Suspected scam or fraud'],
                        ['inappropriate', '🚫', 'Inappropriate content'],
                        ['account',       '👤', 'Account problem'],
                        ['payment',       '💳', 'Payment / Escrow issue'],
                        ['other',         '📋', 'Other'],
                    ];
                    foreach ($cats as [$val, $icon, $label]):
                    ?>
                    <label class="radio-card <?= $fCategory===$val?'selected':'' ?>">
                        <input type="radio" name="category" value="<?= $val ?>"
                               <?= $fCategory===$val?'checked':'' ?> required>
                        <span class="rc-icon"><?= $icon ?></span>
                        <span class="rc-label"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Subject ── -->
            <div class="cr-section">
                <div class="cr-section-title">Report title</div>
                <input type="text" name="reportTitle" class="cr-input"
                       value="<?= h($fTitle) ?>"
                       placeholder="Brief summary of the issue" maxlength="120" required>
            </div>

            <!-- ── Who / What are you reporting? ── -->
            <div class="cr-section">
                <div class="cr-section-title">
                    Who or what are you reporting?
                    <span class="optional-tag">optional</span>
                </div>

                <?php if ($prefillSeller): ?>
                    <!-- Pre-filled from ViewItem -->
                    <input type="hidden" name="reportedUserID" value="<?= $prefillSeller ?>">
                    <input type="hidden" name="reportedItemID" value="<?= $prefillItem ?? '' ?>">
                    <div class="prefill-card">
                        <div class="pf-row">
                            <span class="pf-label">Seller</span>
                            <span class="pf-val">@<?= h($prefillSellerName) ?> (ID <?= $prefillSeller ?>)</span>
                        </div>
                        <?php if ($prefillItem): ?>
                        <div class="pf-row">
                            <span class="pf-label">Item</span>
                            <span class="pf-val"><?= h($prefillItemName) ?> (ID <?= $prefillItem ?>)</span>
                        </div>
                        <?php endif; ?>
                        <a href="createReport.php" class="pf-clear">✕ Clear pre-fill</a>
                    </div>

                <?php else: ?>
                    <!-- Standard search mode -->
                    <div class="search-section">
                        <label class="cr-label">Search user by username</label>
                        <div class="user-search-wrap">
                            <input type="text" id="userSearchInput" class="cr-input"
                                   placeholder="Type a username…" autocomplete="off">
                            <div class="user-dropdown" id="userDropdown"></div>
                        </div>
                        <input type="hidden" name="reportedUserID" id="reportedUserID"
                               value="<?= h($fRepUserID ?? '') ?>">
                        <div class="selected-user" id="selectedUserDisplay" style="display:none"></div>
                    </div>

                    <div class="search-section" id="itemSearchSection" style="display:none">
                        <label class="cr-label">Select an item by this user (optional)</label>
                        <select name="reportedItemID" id="reportedItemID" class="cr-input cr-select">
                            <option value="">— No specific item —</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ── Description ── -->
            <div class="cr-section">
                <div class="cr-section-title">Describe the issue</div>
                <textarea name="reportDescription" class="cr-textarea" rows="6"
                          placeholder="Please provide as much detail as possible — what happened, when, and any other relevant information…"
                          maxlength="2000" required><?= h($fDesc) ?></textarea>
                <span class="char-count" id="descCount"><?= mb_strlen($fDesc) ?> / 2000</span>
            </div>

            <button type="submit" class="cr-submit">Submit report</button>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php include '../HeaderAndFooter/Footer.php'; ?>

<script>
const ALL_USERS = <?= $usersJson ?>;
const ALL_ITEMS = <?= $itemsJson ?>;

/* ── Radio card highlight ── */
document.querySelectorAll('.radio-card input[type=radio]').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.radio-card').forEach(c => c.classList.remove('selected'));
        r.closest('.radio-card').classList.add('selected');
    });
});

/* ── Char counter ── */
const descTA = document.querySelector('textarea[name="reportDescription"]');
const descCt = document.getElementById('descCount');
if (descTA) {
    descTA.addEventListener('input', () => {
        descCt.textContent = `${descTA.value.length} / 2000`;
    });
}

/* ── User search ── */
const userInput     = document.getElementById('userSearchInput');
const userDropdown  = document.getElementById('userDropdown');
const hiddenUserID  = document.getElementById('reportedUserID');
const userDisplay   = document.getElementById('selectedUserDisplay');
const itemSection   = document.getElementById('itemSearchSection');
const itemSelect    = document.getElementById('reportedItemID');
const reportLocationBtn = document.getElementById('reportLocationBtn');

if (reportLocationBtn) {
    reportLocationBtn.addEventListener('click', () => {
        const titleEl = document.querySelector('input[name="reportTitle"]');
        const descEl  = document.querySelector('textarea[name="reportDescription"]');
        const category = document.querySelector('input[name="category"][value="other"]');
        if (titleEl) titleEl.value = 'Missing location on TradeSA';
        if (descEl) descEl.value = 'I could not find my province/city/suburb in the location selector. Please add the missing location to the list.';
        if (category) category.checked = true;
        if (userInput) userInput.focus();
    });
}

if (userInput) {
    let selectedUser = null;

    userInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        userDropdown.innerHTML = '';
        if (!q) { userDropdown.style.display = 'none'; return; }

        const matches = ALL_USERS.filter(u =>
            u.username.toLowerCase().includes(q) ||
            (u.name  || '').toLowerCase().includes(q) ||
            (u.surname || '').toLowerCase().includes(q)
        ).slice(0, 8);

        if (!matches.length) { userDropdown.style.display = 'none'; return; }

        matches.forEach(u => {
            const li = document.createElement('div');
            li.className = 'udrop-item';
            li.innerHTML = `<strong>@${u.username}</strong> <span>${u.name||''} ${u.surname||''}</span>`;
            li.addEventListener('mousedown', e => {
                e.preventDefault();
                selectUser(u);
            });
            userDropdown.appendChild(li);
        });
        userDropdown.style.display = 'block';
    });

    userInput.addEventListener('blur', () => {
        setTimeout(() => { userDropdown.style.display = 'none'; }, 150);
    });

    function selectUser(u) {
        selectedUser = u;
        hiddenUserID.value = u.userID;
        userInput.value = '';
        userDropdown.style.display = 'none';

        userDisplay.style.display = 'flex';
        userDisplay.innerHTML = `
            <span>👤 <strong>@${u.username}</strong> — ${u.name||''} ${u.surname||''}</span>
            <button type="button" onclick="clearUser()">✕</button>`;

        /* Populate item dropdown */
        const userItems = ALL_ITEMS.filter(i => parseInt(i.userID) === parseInt(u.userID));
        itemSelect.innerHTML = '<option value="">— No specific item —</option>';
        userItems.forEach(i => {
            const o = document.createElement('option');
            o.value = i.itemID;
            o.textContent = i.itemName;
            itemSelect.appendChild(o);
        });
        itemSection.style.display = userItems.length ? 'block' : 'none';
    }

    window.clearUser = function () {
        selectedUser = null;
        hiddenUserID.value = '';
        userDisplay.style.display = 'none';
        itemSection.style.display = 'none';
        itemSelect.innerHTML = '<option value="">— No specific item —</option>';
    };
}
</script>
</body>
</html>