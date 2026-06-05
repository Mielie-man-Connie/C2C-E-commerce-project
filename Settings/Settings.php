<?php
    /* Settings.php - Lets the user update fields of their account. */
    /* Session Management */
    if (session_status() === PHP_SESSION_NONE) session_start();

    if (!isset($_SESSION['user_id'])) {
        header('Location: ../LoginAndRegister/LoginPage.php');
        exit;
    }

    /* Database Connection */
    require_once __DIR__ . '/../data/database.php';
    require_once __DIR__ . '/../data/imageHelper.php';

    $userId = (int) $_SESSION['user_id'];
    $errors  = [];
    $success = '';
    $imageUploadNotice = '';

    /* Helper Function */
    function h(string $v): string {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    function parseZAID(string $id): array {
        if (!preg_match('/^\d{13}$/', $id)) return ['valid' => false, 'dob' => null];
        $digits = str_split($id);
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $d = (int)$digits[$i];
            if ($i % 2 === 0) { $sum += $d; }
            else { $d *= 2; $sum += ($d > 9) ? $d - 9 : $d; }
        }
        if ($sum % 10 !== 0) return ['valid' => false, 'dob' => null];
        $yr = (int)substr($id,0,2); $mo = (int)substr($id,2,2); $dy = (int)substr($id,4,2);
        $fy = ($yr <= 24) ? 2000 + $yr : 1900 + $yr;
        if (!checkdate($mo,$dy,$fy)) return ['valid' => false, 'dob' => null];
        return ['valid' => true, 'dob' => sprintf('%04d-%02d-%02d',$fy,$mo,$dy)];
    }

    /* Fetch user data and location list for form population.
       Build location tree and map for client-side use in the form. */
    try {
        $stmt = $pdo->prepare(
            'SELECT username, email, name, surname, dob, mobile, zaID, locationID,
                    profileDescription, imageID, isVerified
            FROM accounts WHERE userID = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) { header('Location: ../data/logout.php'); exit; }
        
      /* Fetch user's average review score */
    try {
        // FIX: JOIN the items table to find reviews belonging to this user's listings
        $revStmt = $pdo->prepare('
            SELECT AVG(r.reviewScore) as avgScore, COUNT(*) as cnt 
            FROM reviews r
            JOIN items i ON r.itemID = i.itemID
            WHERE i.userID = :uid
        ');
        $revStmt->execute([':uid' => $userId]);
        $revRow = $revStmt->fetch(PDO::FETCH_ASSOC);
        $userAvgScore = $revRow['avgScore'] ? round((float)$revRow['avgScore'], 1) : null;
        $userReviewCount = (int)($revRow['cnt'] ?? 0);
    } catch (PDOException $e) {
        $userAvgScore = null;
        $userReviewCount = 0;
    }

        
        try {
            $locStmt = $pdo->query('SELECT locationID, province, city, suburb FROM locations ORDER BY province, city, suburb');
            $locations = $locStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $locations = [];
        }
    } catch (PDOException $e) {
        $errors[] = 'DB error: ' . $e->getMessage();
    }

    $locTree = [];
    $locMap  = [];

    /* Build location tree and map for client-side use in the form */
    foreach ($locations as $loc) {
        $prov = $loc['province'] ?? '';
        $city = $loc['city'] ?? '';
        $locTree[$prov][$city][] = [
            'id'     => (int)$loc['locationID'], 
            'suburb' => $loc['suburb'] ?? '',
        ];
        $locMap[(int)$loc['locationID']] = [
            'province' => $prov,
            'city'     => $city,
            'suburb'   => $loc['suburb'] ?? '',
        ];
    }

    /* Encode location data for client-side use in the form */
    $locTreeJson = json_encode($locTree, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    $locMapJson  = json_encode($locMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    /* Handle form submissions */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        /* Profile Update */
        if ($action === 'profile') {
            $name        = trim($_POST['name']        ?? '');
            $surname     = trim($_POST['surname']      ?? '');
            $dob         = trim($_POST['dob']          ?? '');
            $mobile      = preg_replace('/\D/', '', trim($_POST['mobile'] ?? ''));
            $zaID        = preg_replace('/\D/', '', trim($_POST['zaID'] ?? ''));
            $locationID  = filter_input(INPUT_POST, 'locationID', FILTER_VALIDATE_INT);
            if (($locationID === null || $locationID === false) && isset($_POST['suburbSelect']) && preg_match('/^\d+$/', $_POST['suburbSelect'])) {
                $locationID = (int) $_POST['suburbSelect'];
            }
            if ($locationID === false) {
                $locationID = null;
            }
            $description = trim($_POST['description']  ?? '');

            /* Required fields validation */
            if (!$name)    $errors[] = 'First name is required.';
            if (!$surname) $errors[] = 'Surname is required.';
            if (!$dob)     $errors[] = 'Date of birth is required.';
            elseif (strtotime($dob) >= strtotime('today')) $errors[] = 'Date of birth must be in the past.';

            /* Optional validated fields */
            $idParsed   = null;
            $idProvided = $zaID !== '';
            $mobProvided= $mobile !== '';

            /* Validate mobile and zaID formats if provided */
            if ($idProvided) {
                $idParsed = parseZAID($zaID);
                if (!$idParsed['valid']) {
                    $errors[] = 'SA ID number is not valid.';
                    $zaID = ''; $idParsed = null;
                } elseif ($dob && $idParsed['dob'] !== $dob) {
                    $errors[] = 'ID number does not match date of birth. Both fields cleared.';
                    $dob = ''; $zaID = ''; $idParsed = null;
                }
            }
            if ($mobProvided && !preg_match('/^0[6-8][0-9]{8}$/', $mobile)) {
                $errors[] = 'Mobile must be a valid 10-digit SA number.';
                $mobile = '';
            }

            /* Validate locationID if provided */
            if ($locationID !== null) {
                try {
                    $s = $pdo->prepare('SELECT locationID FROM locations WHERE locationID=:lid LIMIT 1');
                    $s->execute([':lid'=>$locationID]);
                    if (!$s->fetch()) {
                        $errors[] = 'Selected location is invalid.';
                        $locationID = null;
                    }
                } catch (PDOException $e) {
                    $errors[] = 'DB error: '.$e->getMessage();
                    $locationID = null;
                }
            }

            /* If no validation errors, check for uniqueness of mobile and zaID if they were provided */
            if (empty($errors)) {
                /* Check for uniqueness of mobile and zaID if provided */
                try {
                    if ($mobProvided && $mobile) {
                        $s = $pdo->prepare('SELECT userID FROM accounts WHERE mobile=:m AND userID!=:u LIMIT 1');
                        $s->execute([':m'=>$mobile,':u'=>$userId]);
                        if ($s->fetch()) $errors[] = 'That mobile number is already linked to another account.';
                    }
                    if ($idProvided && $zaID) {
                        $s = $pdo->prepare('SELECT userID FROM accounts WHERE zaID=:i AND userID!=:u LIMIT 1');
                        $s->execute([':i'=>$zaID,':u'=>$userId]);
                        if ($s->fetch()) $errors[] = 'That ID number is already linked to another account.';
                    }
                } catch (PDOException $e) { $errors[] = 'DB error: '.$e->getMessage(); }
            }

            /* Handle profile image upload with compression if provided */
            $newImageID = null;
            if (empty($errors) && isset($_FILES['profileImage']) && $_FILES['profileImage']['error'] === UPLOAD_ERR_OK) {
                // Function = detectMime(), Class = data/imageHelper.php
                $mime = detectMime($_FILES['profileImage']['tmp_name'], $_FILES['profileImage']['type'] ?? null);
                if (!in_array($mime, ['image/jpeg','image/png','image/webp','image/gif'])) {
                    $errors[] = 'Image must be JPEG, PNG, WebP or GIF.';
                } elseif ($_FILES['profileImage']['size'] > 5*1024*1024) {
                    $errors[] = 'Image must be under 5 MB.';
                } else {
                    if (!function_exists('imagecreatetruecolor')) {
                        $imageUploadNotice = 'Profile image saved without compression because server GD support is unavailable.';
                    }
                    // Use image compression helper (compresses to max 512x512)
                    // Function = compressAndStoreImage(), Class = data/imageHelper.php
                    $newImageID = compressAndStoreImage($pdo, $_FILES['profileImage']['tmp_name'], $mime, $userId);
                    if ($newImageID === null) {
                        $errors[] = 'Failed to compress and save profile image. Please try again.';
                    }
                }
            }

            /* If no errors, update the account and log changes in the audit table */
            if (empty($errors)) {
                $isVerified = ($name && $surname && $dob && $mobProvided && $mobile && $idProvided && $zaID && $idParsed) ? 1 : 0;
                try {
                    $oldStmt = $pdo->prepare('SELECT name, surname, dob, mobile, zaID, locationID, profileDescription, imageID FROM accounts WHERE userID=:uid LIMIT 1');
                    $oldStmt->execute([':uid'=>$userId]);
                    $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $sql = 'UPDATE accounts SET
                                name=:name, surname=:surname, dob=:dob,
                                mobile=:mobile, zaID=:zaID, locationID=:locationID,
                                profileDescription=:desc, isVerified=:ver'
                        . ($newImageID !== null ? ', imageID=:img' : '')
                        . ' WHERE userID=:uid';
                    $p = [
                        ':name'=>$name, ':surname'=>$surname,
                        ':dob'=>$dob ?: null, ':mobile'=>$mobile ?: null,
                        ':zaID'=>$zaID ?: null, ':locationID'=>$locationID !== null ? $locationID : null,
                        ':desc'=>$description ?: null, ':ver'=>$isVerified, ':uid'=>$userId,
                    ];
                    if ($newImageID !== null) $p[':img'] = $newImageID;
                    $pdo->prepare($sql)->execute($p);
                    $success = 'Profile updated successfully.';

                    $changes = [];
                    $fields = [
                        'name' => $name,
                        'surname' => $surname,
                        'dob' => $dob ?: null,
                        'mobile' => $mobile ?: null,
                        'zaID' => $zaID ?: null,
                        'locationID' => $locationID !== null ? $locationID : null,
                        'profileDescription' => $description ?: null,
                    ];
                    if ($newImageID) {
                        $fields['imageID'] = $newImageID;
                    }
                    foreach ($fields as $col => $newVal) {
                        $oldVal = $oldUser[$col] ?? null;
                        if ($oldVal !== $newVal) {
                            $changes[] = "$col: '" . ($oldVal ?? '(null)') . "' → '" . ($newVal ?? '(null)') . "'";
                        }
                    }
                    if ($changes) {
                        // Function = logAudit(), Class = data/database.php
                        logAudit($pdo, $userId, 'modified', 'accounts: userID='.$userId, implode(' | ', $changes));
                    }

                    /* Reload user data */
                    $stmt->execute([':id'=>$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (PDOException $e) { $errors[] = 'Update error: '.$e->getMessage(); }
            }
        }

        /* Password Change */
        if ($action === 'password') {
            $current  = $_POST['currentPassword']  ?? '';
            $newPass  = $_POST['newPassword']       ?? '';
            $confirm  = $_POST['confirmPassword']   ?? '';

            try {
                $s = $pdo->prepare('SELECT password_hash FROM accounts WHERE userID=:id LIMIT 1');
                $s->execute([':id'=>$userId]);
                $row = $s->fetch(PDO::FETCH_ASSOC);

                if (!$row || !password_verify($current, $row['password_hash'])) {
                    $errors[] = 'Current password is incorrect.';
                } elseif (strlen($newPass) < 8) {
                    $errors[] = 'New password must be at least 8 characters.';
                } elseif (!preg_match('/[A-Z]/',$newPass)) {
                    $errors[] = 'New password needs at least one uppercase letter.';
                } elseif (!preg_match('/[0-9]/',$newPass)) {
                    $errors[] = 'New password needs at least one number.';
                } elseif ($newPass !== $confirm) {
                    $errors[] = 'Passwords do not match.';
                } else {
                    $hash = password_hash($newPass, PASSWORD_DEFAULT);
                    $pdo->prepare('UPDATE accounts SET password_hash=:h WHERE userID=:id')
                        ->execute([':h'=>$hash, ':id'=>$userId]);
                    $success = 'Password changed successfully.';
                    
                        /* Log audit: password changed */
                        // Function = logAudit(), Class = data/database.php
                        logAudit($pdo, $userId, 'modified', 'accounts: userID='.$userId, 'Changed account password');
                }
            } catch (PDOException $e) { $errors[] = 'DB error: '.$e->getMessage(); }
        }
    }

    /* Populate form fields (use POST values on error, DB values otherwise) */
    $f = [
        'name'        => h($errors && isset($_POST['name'])        ? $_POST['name']        : ($user['name']               ?? '')),
        'surname'     => h($errors && isset($_POST['surname'])     ? $_POST['surname']     : ($user['surname']            ?? '')),
        'dob'         => h($errors && isset($_POST['dob'])         ? $_POST['dob']         : ($user['dob']                ?? '')),
        'mobile'      => h($errors && isset($_POST['mobile'])      ? $_POST['mobile']      : ($user['mobile']             ?? '')),
        'zaID'        => h($errors && isset($_POST['zaID'])        ? $_POST['zaID']        : ($user['zaID']               ?? '')),
        'locationID'  => h($errors && isset($_POST['locationID'])  ? $_POST['locationID']  : ($user['locationID']         ?? '')),
        'description' => h($errors && isset($_POST['description']) ? $_POST['description'] : ($user['profileDescription'] ?? '')),
    ];
    $isVerified = (bool)($user['isVerified'] ?? false);
    $imageUrl   = !empty($user['imageID']) ? '../data/getImage.php?id='.(int)$user['imageID'] : null;
?>

<!-- Settings.php HTML -->

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>TradeSA – Settings</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="Settings.css">
        <link rel="stylesheet" href="../data/preset.css">
        <script src="../data/translations.js"></script>
    </head>
    <body>
        <?php include '../HeaderAndFooter/Header.php'; ?>

        <?php if ($imageUploadNotice && empty($errors) && $success): ?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showToast === 'function') {
                        showToast('<?= h($imageUploadNotice) ?>');
                    }
                });
            </script>
        <?php endif; ?>

        <div class="settings-wrap">

            <!-- Left nav -->
            <nav class="settings-nav">
                <button class="snav-item active" data-tab="profile">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    Profile
                </button>
                <button class="snav-item" data-tab="security">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    Security
                </button>
            </nav>

            <!-- Content area -->
            <div class="settings-content">

                <!-- Feedback -->
                <?php if (!empty($errors)): ?>
                    <div class="feedback error" role="alert">
                        <?php foreach ($errors as $e): ?><p>⚠ <?= $e ?></p><?php endforeach; ?>
                    </div>
                <?php elseif ($success): ?>
                    <div class="feedback success" role="status"><p>✓ <?= h($success) ?></p></div>
                <?php endif; ?>

                <!-- ══ PROFILE TAB ══ -->
                <section class="settings-tab active" id="tab-profile">
                    <div class="tab-header">
                        <h2 data-i18n="profileSettings">Profile information</h2>
                        <?php if ($isVerified): ?>
                            <span class="verified-pill verified" data-i18n="verifiedAccount">✓ Verified account</span>
                        <?php else: ?>
                            <span class="verified-pill unverified" data-i18n="notVerified">⚠ Not verified</span>
                        <?php endif; ?>
                    </div>

                    <!-- Review Rating Card -->
                    <?php if ($userAvgScore !== null): ?>
                    <div class="review-rating-card">
                        <div class="rating-display">
                            <span class="rating-star">⭐</span>
                            <div class="rating-info">
                                <span class="rating-number"><?= number_format($userAvgScore, 1) ?>/10</span>
                                <span class="rating-subtext"><?= $userReviewCount ?> review<?= $userReviewCount !== 1 ? 's' : '' ?> from buyers</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form action="Settings.php" method="POST" enctype="multipart/form-data" novalidate>
                        <input type="hidden" name="action" value="profile">

                        <!-- Avatar row -->
                        <div class="avatar-row">
                            <div class="avatar-circle" id="avatarCircle">
                                <?php if ($imageUrl): ?>
                                    <img src="<?= h($imageUrl) ?>" alt="Profile" id="avatarImg">
                                <?php else: ?>
                                    <span id="avatarInitial"><?= h(strtoupper(mb_substr($f['name'] ?: 'U', 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="avatar-actions">
                                <input type="file" name="profileImage" id="profileImageInput" accept="image/*" hidden>
                                <button type="button" class="btn-outline" onclick="document.getElementById('profileImageInput').click()" data-i18n="changePhoto">Change photo</button>
                                <p class="hint" data-i18n="photoHint">JPG · PNG · WebP — max 5 MB · optional</p>
                            </div>
                        </div>

                        <!-- Name row -->
                        <div class="field-grid two-col">
                            <div class="field-group">
                                <label data-i18n="firstName">First name <span class="req">*</span></label>
                                <input type="text" name="name" value="<?= $f['name'] ?>" placeholder="e.g. Sipho" maxlength="50" required>
                            </div>
                            <div class="field-group">
                                <label data-i18n="surname">Surname <span class="req">*</span></label>
                                <input type="text" name="surname" value="<?= $f['surname'] ?>" placeholder="e.g. Dlamini" maxlength="50" required>
                            </div>
                        </div>

                        <!-- Read-only account fields -->
                        <div class="field-grid two-col">
                            <div class="field-group">
                                <label data-i18n="username">Username</label>
                                <input type="text" value="<?= h($user['username'] ?? '') ?>" disabled>
                            </div>
                            <div class="field-group">
                                <label data-i18n="email">Email</label>
                                <input type="email" value="<?= h($user['email'] ?? '') ?>" disabled>
                                <span class="hint" data-i18n="emailNoChange">Email cannot be changed.</span>
                            </div>
                        </div>

                        <div class="section-divider" data-i18n="required">Required</div>
                        <div class="field-grid two-col">
                            <div class="field-group">
                                <label data-i18n="dateOfBirth">Date of birth <span class="req">*</span></label>
                                <input type="date" name="dob" value="<?= $f['dob'] ?>"
                                    max="<?= date('Y-m-d', strtotime('-13 years')) ?>" required>
                            </div>
                        </div>

                        <div class="section-divider">
                            <span data-i18n="optionalVerified">Optional — complete all four for</span>
                            <span class="verified-inline">✓ <span data-i18n="verified">Verified</span></span> <span data-i18n="status">status</span>
                        </div>
                        <div class="field-grid two-col">
                            <div class="field-group">
                                <label data-i18n="mobileNumber">Mobile number</label>
                                <input type="tel" name="mobile" value="<?= $f['mobile'] ?>" placeholder="0821234567" maxlength="10">
                                <span class="hint" data-i18n="mobileHint">10-digit SA number</span>
                            </div>
                            <div class="field-group">
                                <label data-i18n="saIdNumber">SA ID number</label>
                                <input type="text" name="zaID" value="<?= $f['zaID'] ?>" placeholder="8001015009087" maxlength="13" inputmode="numeric">
                                <span class="hint" data-i18n="saIdHint">13 digits · must match date of birth</span>
                            </div>
                            <div class="field-group span-full">
                                <label data-i18n="location">Location</label>
                                <div class="field-grid location-picker">
                                    <div class="field-group">
                                        <label data-i18n="province">Province</label>
                                        <select id="provinceSelect" class="ep-input" name="provinceSelect"></select>
                                    </div>
                                    <div class="field-group">
                                        <label data-i18n="city">City</label>
                                        <select id="citySelect" class="ep-input" name="citySelect" disabled></select>
                                    </div>
                                    <div class="field-group">
                                        <label data-i18n="suburb">Suburb</label>
                                        <select id="suburbSelect" class="ep-input" name="suburbSelect" disabled></select>
                                    </div>
                                </div>
                                <input type="hidden" name="locationID" id="locationID" value="<?= $f['locationID'] ?>">
                                <span class="hint" data-i18n="locationHint">Select your saved TradeSA location. If your location is missing, report it.</span>
                                <a href="../CreateReport/CreateReport.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>&reportType=location" class="btn-outline" style="margin-top:0.85rem;display:inline-flex;" data-i18n="reportMissing">Report missing location</a>
                            </div>
                            <div class="field-group span-full">
                                <label data-i18n="profileDescription">Profile description</label>
                                <textarea name="description" rows="3" maxlength="500" placeholder="Tell other traders about yourself…"><?= $f['description'] ?></textarea>
                                <span class="hint char-count" id="descCount"><?= mb_strlen($f['description']) ?> / 500</span>
                            </div>
                        </div>

                        <!-- Verified status notice -->
                        <div class="verified-notice" id="verNotice" aria-live="polite"></div>

                        <button type="submit" class="btn-primary" data-i18n="saveChanges">Save changes</button>
                    </form>
                </section>

                <!-- ══ SECURITY TAB ══ -->
                <section class="settings-tab" id="tab-security">
                    <div class="tab-header"><h2 data-i18n="changePassword">Change password</h2></div>

                    <form action="Settings.php" method="POST" novalidate>
                        <input type="hidden" name="action" value="password">
                        <div class="field-grid one-col">
                            <div class="field-group">
                                <label data-i18n="currentPassword">Current password</label>
                                <input type="password" name="currentPassword" placeholder="Enter current password" required>
                            </div>
                            <div class="field-group">
                                <label data-i18n="newPassword">New password</label>
                                <input type="password" name="newPassword" id="newPass" placeholder="At least 8 characters" required>
                                <div class="password-strength" id="passStrength"></div>
                            </div>
                            <div class="field-group">
                                <label data-i18n="confirmPassword">Confirm new password</label>
                                <input type="password" name="confirmPassword" placeholder="Repeat new password" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-primary" data-i18n="updatePassword">Update password</button>
                    </form>
                </section>

            </div><!-- /.settings-content -->
        </div><!-- /.settings-wrap -->

        <?php include '../HeaderAndFooter/Footer.php'; ?>

        <script>
        /* ── Tab switching ── */
        document.querySelectorAll('.snav-item').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.snav-item').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('tab-' + this.dataset.tab).classList.add('active');
            });
        });

        /* Activate the correct tab on error */
        <?php if (!empty($errors) && ($_POST['action'] ?? '') === 'password'): ?>
        document.querySelector('[data-tab="security"]').click();
        <?php endif; ?>

        /* ── Location selector ── */
        const LOC_TREE      = <?= $locTreeJson ?>;
        const LOC_MAP       = <?= $locMapJson ?>;
        const provinceSelect = document.getElementById('provinceSelect');
        const citySelect     = document.getElementById('citySelect');
        const suburbSelect   = document.getElementById('suburbSelect');
        const locationIDInput= document.getElementById('locationID');
        const preLocationID  = <?= json_encode($f['locationID'] !== '' ? (int)$f['locationID'] : null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;

        function resetCitySelect() {
            citySelect.innerHTML = '<option value="">Choose city</option>';
            citySelect.disabled = true;
            resetSuburbSelect();
        }
        function resetSuburbSelect() {
            suburbSelect.innerHTML = '<option value="">Choose suburb</option>';
            suburbSelect.disabled = true;
        }
        function populateCities(province, selectedCity = '') {
            resetCitySelect();
            if (!province || !LOC_TREE[province]) return;
            Object.keys(LOC_TREE[province]).sort().forEach(city => {
                const opt = document.createElement('option');
                opt.value = city;
                opt.textContent = city;
                if (city === selectedCity) opt.selected = true;
                citySelect.appendChild(opt);
            });
            citySelect.disabled = false;
        }
        function populateSuburbs(province, city, selectedId = null) {
            resetSuburbSelect();
            if (!province || !city || !LOC_TREE[province]?.[city]) return;
            LOC_TREE[province][city].forEach(entry => {
                const opt = document.createElement('option');
                opt.value = entry.id;
                opt.textContent = entry.suburb;
                if (String(entry.id) === String(selectedId)) opt.selected = true;
                suburbSelect.appendChild(opt);
            });
            suburbSelect.disabled = false;
        }
        function setLocationId(value) {
            locationIDInput.value = value ? String(value) : '';
        }
        provinceSelect.addEventListener('change', () => {
            populateCities(provinceSelect.value);
            setLocationId(null);
        });
        citySelect.addEventListener('change', () => {
            populateSuburbs(provinceSelect.value, citySelect.value);
            setLocationId(null);
        });
        suburbSelect.addEventListener('change', () => {
            setLocationId(suburbSelect.value || null);
        });
        const profileForm = document.querySelector('form[action="Settings.php"]');
        if (profileForm) {
            profileForm.addEventListener('submit', () => {
                if (suburbSelect.value) {
                    locationIDInput.value = suburbSelect.value;
                }
            });
        }
        function initLocationSelector() {
            provinceSelect.innerHTML = '<option value="">Choose province</option>';
            Object.keys(LOC_TREE).sort().forEach(province => {
                const opt = document.createElement('option');
                opt.value = province;
                opt.textContent = province;
                provinceSelect.appendChild(opt);
            });
            resetCitySelect();
            if (preLocationID && LOC_MAP[preLocationID]) {
                const loc = LOC_MAP[preLocationID];
                provinceSelect.value = loc.province;
                populateCities(loc.province, loc.city);
                populateSuburbs(loc.province, loc.city, preLocationID);
                setLocationId(preLocationID);
            }
        }
        initLocationSelector();

        /* ── Avatar preview ── */
        document.getElementById('profileImageInput').addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                const circle = document.getElementById('avatarCircle');
                circle.innerHTML = `<img src="${e.target.result}" alt="Preview" id="avatarImg">`;
            };
            reader.readAsDataURL(file);
        });

        /* ── Char counter ── */
        const descTA    = document.querySelector('textarea[name="description"]');
        const descCount = document.getElementById('descCount');
        if (descTA) {
            descTA.addEventListener('input', () => {
                descCount.textContent = `${descTA.value.length} / 500`;
            });
        }

        /* ── Live verified notice ── */
        const dobEl    = document.querySelector('input[name="dob"]');
        const mobileEl = document.querySelector('input[name="mobile"]');
        const zaIDEl  = document.querySelector('input[name="zaID"]');
        const notice   = document.getElementById('verNotice');

        function updateVerNotice() {
            if (!dobEl || !notice) return;
            const dob    = dobEl.value;
            const mobile = (mobileEl?.value || '').replace(/\D/g,'');
            const id     = (zaIDEl?.value  || '').trim();
            const filled = dob && mobile.length === 10 && id.length === 13;
            if (filled) {
                notice.className = 'verified-notice show is-verified';
                notice.textContent = '✓ All optional fields complete — your account will be Verified.';
            } else {
                const miss = [];
                if (!dob)              miss.push('date of birth');
                if (mobile.length<10)  miss.push('mobile');
                if (id.length<13)      miss.push('SA ID');
                notice.className = 'verified-notice show';
                notice.textContent = miss.length ? `Still needed for Verified: ${miss.join(', ')}.` : '';
            }
        }
        [dobEl, mobileEl, zaIDEl].forEach(el => el?.addEventListener('input', updateVerNotice));
        updateVerNotice();

        /* ── Password strength indicator ── */
        const newPassEl  = document.getElementById('newPass');
        const strengthEl = document.getElementById('passStrength');
        if (newPassEl) {
            newPassEl.addEventListener('input', function () {
                const v = this.value;
                let score = 0;
                if (v.length >= 8)        score++;
                if (/[A-Z]/.test(v))      score++;
                if (/[0-9]/.test(v))      score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;
                const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
                const classes = ['', 'weak', 'fair', 'good', 'strong'];
                strengthEl.textContent  = v ? labels[score] : '';
                strengthEl.className    = 'password-strength ' + (v ? classes[score] : '');
            });
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